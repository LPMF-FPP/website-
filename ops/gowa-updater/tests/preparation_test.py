import base64
import copy
import importlib.machinery
import importlib.util
from pathlib import Path
import subprocess
import tempfile
import unittest
from unittest.mock import patch


source = Path(__file__).parents[1] / 'lpmf-gowa-prepare'
loader = importlib.machinery.SourceFileLoader('gowa_prepare', str(source))
spec = importlib.util.spec_from_loader(loader.name, loader)
prepare = importlib.util.module_from_spec(spec)
loader.exec_module(prepare)


class PreparationTest(unittest.TestCase):
    def setUp(self):
        self.previous = prepare.REPOSITORY + '@sha256:' + 'a' * 64
        self.digest = 'sha256:' + 'b' * 64
        self.catalog = dict(schema_version=1, signature_valid=True, generation='old',
                            approved_registry='docker.io', approved_repository=prepare.REPOSITORY,
                            platform='linux/amd64', revocation_generation='initial',
                            signature=dict(algorithm='ed25519', key_id='operator', value='signature'),
                            releases=[dict(release_id='current', version='v9.3.0', digest='sha256:' + 'a' * 64,
                                           image=self.previous, approved=True, revoked=False,
                                           revocation_generation='initial')])
        self.rollback = dict(previous_image=self.previous, mounts=[], catalog_generation='old')
        self.envelope = dict(image_override=str(prepare.ROOT / 'override.json'))
        self.patches = [
            patch.object(prepare, 'verify_installation'),
            patch.object(prepare, 'read', side_effect=lambda path: copy.deepcopy({
                'catalog.json': self.catalog, 'compose-envelope.json': self.envelope,
                'rollback-manifest.json': self.rollback}[Path(path).name])),
            patch.object(prepare, 'verify'), patch.object(prepare, 'sign', return_value='new-signature'),
            patch.object(prepare, 'assert_no_update'),
            patch.object(prepare, 'baseline', return_value=(self.previous, 'container:config-id')),
            patch.object(prepare, 'stable_release', return_value='c' * 40),
            patch.object(prepare, 'immutable_target', return_value=self.digest),
            patch.object(prepare, 'image_info', return_value={'Config': {'Labels': {}}}),
            patch.object(prepare, 'run'), patch.object(prepare, 'atomic'), patch.object(prepare, 'publish'),
        ]
        for item in self.patches:
            item.start()
            self.addCleanup(item.stop)

    def test_prepares_latest_without_stopping_or_recreating_the_service(self):
        prepare.prepare('v9.7.0')
        prepare.run.assert_called_once_with('/usr/bin/docker', 'pull', '--platform', 'linux/amd64',
                                            prepare.REPOSITORY + '@' + self.digest, timeout=600)
        self.assertEqual(prepare.publish.call_args.args, ('ready', 'v9.7.0'))
        self.assertEqual(prepare.publish.call_args.kwargs['previous_digest'], 'sha256:' + 'a' * 64)
        self.assertEqual(prepare.publish.call_args.kwargs['container_identity'], 'container:config-id')
        published_catalog = prepare.atomic.call_args_list[-1].args[1]
        self.assertEqual(published_catalog['releases'][0], self.catalog['releases'][0])
        self.assertEqual(published_catalog['releases'][-1]['upstream_commit_sha'], 'c' * 40)
        self.assertEqual(published_catalog['signature']['value'], 'new-signature')

    def assert_not_ready(self):
        self.assertFalse(any(call.args[0] == 'ready' for call in prepare.publish.call_args_list))
        prepare.atomic.assert_not_called()

    def test_refuses_catalog_with_invalid_signature(self):
        prepare.verify.side_effect = prepare.Rejected()
        with self.assertRaises(prepare.Rejected):
            prepare.prepare('v9.7.0')
        self.assert_not_ready()

    def test_refuses_signing_key_that_does_not_match_catalog_public_key(self):
        prepare.verify.side_effect = [None, prepare.Rejected()]
        with self.assertRaises(prepare.Rejected):
            prepare.prepare('v9.7.0')
        self.assert_not_ready()

    def test_refuses_active_updates(self):
        prepare.assert_no_update.side_effect = prepare.Rejected()
        with self.assertRaises(prepare.Rejected):
            prepare.prepare('v9.7.0')
        prepare.run.assert_not_called()
        self.assert_not_ready()

    def test_does_not_undo_operator_revocation(self):
        self.catalog['releases'].append(dict(version='v9.7.0', digest=self.digest, revoked=True))
        with self.assertRaises(prepare.Rejected):
            prepare.prepare('v9.7.0')
        prepare.run.assert_not_called()
        self.assert_not_ready()

    def test_rejects_downgrades_and_unknown_runtime_versions(self):
        for version in ['v9.3.0', 'v9.2.0']:
            with self.assertRaises(prepare.Rejected):
                prepare.prepare(version)
        self.catalog['releases'] = []
        with self.assertRaises(prepare.Rejected):
            prepare.prepare('v9.7.0')
        self.assert_not_ready()

    def test_rejects_upstream_tag_or_digest_changes_during_download(self):
        prepare.stable_release.side_effect = ['c' * 40, 'd' * 40]
        with self.assertRaises(prepare.Rejected):
            prepare.prepare('v9.7.0')
        self.assert_not_ready()
        prepare.stable_release.side_effect = None
        prepare.immutable_target.side_effect = [self.digest, 'sha256:' + 'd' * 64]
        with self.assertRaises(prepare.Rejected):
            prepare.prepare('v9.7.0')
        self.assert_not_ready()

    def test_rejects_image_revision_mismatch(self):
        prepare.image_info.return_value = {'Config': {'Labels': {'org.opencontainers.image.revision': 'd' * 40}}}
        with self.assertRaises(prepare.Rejected):
            prepare.prepare('v9.7.0')
        self.assert_not_ready()

    def test_rejects_container_or_rollback_change_before_publication(self):
        prepare.baseline.side_effect = [(self.previous, 'container:config-id'), (self.previous, 'changed')]
        with self.assertRaises(prepare.Rejected):
            prepare.prepare('v9.7.0')
        self.assert_not_ready()

    def test_failed_pull_never_publishes_readiness(self):
        prepare.run.side_effect = subprocess.CalledProcessError(1, 'docker')
        with self.assertRaises(subprocess.CalledProcessError):
            prepare.prepare('v9.7.0')
        self.assert_not_ready()


class VerificationTest(unittest.TestCase):
    def test_duplicate_json_keys_are_rejected(self):
        with self.assertRaises(prepare.Rejected):
            prepare.decode(b'{"approved":false,"approved":true}')

    def test_signed_payload_verifies_and_tampering_fails(self):
        with tempfile.TemporaryDirectory(prefix='gowa-crypto-') as directory:
            key = Path(directory) / 'key'
            pub = Path(directory) / 'pub'
            prepare.run('/usr/bin/openssl', 'genpkey', '-algorithm', 'ED25519', '-out', str(key))
            der = prepare.run('/usr/bin/openssl', 'pkey', '-in', str(key), '-pubout', '-outform', 'DER')
            pub.write_bytes(base64.b64encode(der[-32:]))
            with patch.object(prepare, 'trusted', side_effect=lambda path, **kwargs: Path(path)):
                payload = {'contract': 'gowa-preparation-v1', 'state': 'ready'}
                signature = prepare.sign(payload, key)
                prepare.verify(payload, signature, pub)
                with self.assertRaises(subprocess.CalledProcessError):
                    prepare.verify(dict(payload, state='failed'), signature, pub)

    def test_latest_release_must_be_stable_and_exactly_match_the_confirmed_version(self):
        for payload in [dict(tag_name='v9.8.0'), dict(tag_name='v9.7.0', draft=True),
                        dict(tag_name='v9.7.0', prerelease=True)]:
            with patch.object(prepare, 'upstream', return_value=payload):
                with self.assertRaises(prepare.Rejected):
                    prepare.stable_release('v9.7.0')

    def test_rejects_missing_or_ambiguous_amd64_manifest(self):
        for manifests in [[], [dict(digest='invalid', platform=dict(os='linux', architecture='amd64'))]]:
            with patch.object(prepare, 'run', return_value=prepare.canonical(dict(manifests=manifests))):
                with self.assertRaises(prepare.Rejected):
                    prepare.immutable_target('v9.7.0')


if __name__ == '__main__':
    unittest.main()
