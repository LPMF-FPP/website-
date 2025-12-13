import argparse, base64, json, os, sys, urllib.request, shutil, subprocess
from urllib.error import HTTPError, URLError
from datetime import datetime, timedelta, timezone
from pathlib import Path
from urllib.parse import urljoin

try:
    from jinja2 import Environment, FileSystemLoader, select_autoescape
except ImportError:
    print("Menginstal Jinja2 ...", file=sys.stderr)
    os.system(sys.executable + " -m pip install --quiet jinja2")
    from jinja2 import Environment, FileSystemLoader, select_autoescape

INDO_MONTH = {
    1:"Januari",2:"Februari",3:"Maret",4:"April",5:"Mei",6:"Juni",
    7:"Juli",8:"Agustus",9:"September",10:"Oktober",11:"November",12:"Desember"
}

def now_wib():
    # Asia/Jakarta UTC+7, no DST
    return datetime.now(timezone.utc) + timedelta(hours=7)

def fmt_indo(dt: datetime, with_time=True):
    if with_time:
        return f"{dt.day:02d} {INDO_MONTH[dt.month]} {dt.year} {dt:%H:%M} WIB"
    return f"{dt.day:02d} {INDO_MONTH[dt.month]} {dt.year}"

def to_data_uri(path):
    """Return data URI for a local image. If missing, return 1x1 transparent PNG."""
    if not os.path.exists(path):
        # 1x1 transparent PNG
        transparent_png_b64 = (
            "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR4nGNgYAAAAAMAASsJTYQAAAAASUVORK5CYII="
        )
        return f"data:image/png;base64,{transparent_png_b64}"
    with open(path, "rb") as f:
        b64 = base64.b64encode(f.read()).decode("ascii")
    ext = os.path.splitext(path)[1].lower().lstrip(".")
    mime = {"png":"image/png","jpg":"image/jpeg","jpeg":"image/jpeg","svg":"image/svg+xml"}.get(ext,"application/octet-stream")
    return f"data:{mime};base64,{b64}"

def fetch_json(url, timeout=10):
    with urllib.request.urlopen(url, timeout=timeout) as r:
        return json.loads(r.read().decode("utf-8"))

def join_tests(value):
    if isinstance(value, list):
        return "; ".join([str(v) for v in value if v])
    return str(value) if value else ""

def map_payload(p):
    """Map payload API -> context template. Ubah sesuai field API Anda."""
    def g(*keys, default=""):
        for k in keys:
            if isinstance(k, (tuple, list)):
                # nested path: ("parent","child")
                cur = p
                ok = True
                for kk in k:
                    if isinstance(cur, dict) and kk in cur:
                        cur = cur[kk]
                    else:
                        ok = False
                        break
                if ok and cur:
                    return cur
            if k in p and p[k]:
                return p[k]
        return default

    # Nama + pangkat gabungan
    rank = g("rank","pangkat","customer_rank","")
    name = g("customer_name","nama_pelanggan","nama","")
    customer_rank_name = (rank + " " + name).strip() if rank or name else g("customer_rank_name","", default="")

    # Samples -> ringkas (2 kolom)
    samples = []
    def fmt_number(val):
        if isinstance(val, (int, float)):
            txt = f"{val:.2f}".rstrip("0").rstrip(".")
            return txt
        return val

    for s in p.get("samples", []) or p.get("detail_samples", []) or []:
        code = s.get("code") or s.get("kode") or s.get("name") or s.get("nama")
        desc = s.get("desc") or s.get("deskripsi")
        tests = join_tests(s.get("tests") or s.get("pengujian") or g("tests_summary","ringkasan_pengujian"))
        leftover = s.get("leftover") or s.get("sisa")
        delivered = s.get("quantity_display") or s.get("delivered") or s.get("jumlah")
        if delivered is None:
            delivered = fmt_number(s.get("quantity"))
        testing = s.get("testing_quantity") or s.get("testing_quantity_display") or s.get("jumlah_pengujian")
        testing = fmt_number(testing)
        samples.append({
            "code": code,
            "desc": desc,
            "tests": tests,
            "leftover": leftover,
            "delivered": delivered,
            "testing": testing,
        })
    # Fallback jika API simpan linear
    if not samples and g("kode_sampel_1",""):
        i=1
        while g(f"kode_sampel_{i}",""):
            samples.append({
                "code": g(f"kode_sampel_{i}",""),
                "desc": g(f"deskripsi_{i}",""),
                "tests": g(f"pengujian_{i}", "tests_summary","")
            })
            i+=1

    # Rentang kode & nomor laporan
    codes = []
    for s in samples:
        val = s.get("code") or s.get("name")
        if val:
            codes.append(val)
    sample_code_range = ""
    if codes:
        sample_code_range = f"{codes[0]} s/d {codes[-1]}" if len(codes) > 1 else codes[0]

    report_no_range = g("report_no_range","nomor_laporan_range","")
    if (not report_no_range) and p.get("reports"):
        rnos = [r.get("no") for r in p["reports"] if r.get("no")]
        if rnos:
            report_no_range = f"{rnos[0]} s/d {rnos[-1]}" if len(rnos)>1 else rnos[0]

    ctx = {
        "req_no": g("request_no","nomor_permintaan","kode_permintaan","REQ-XXXX-XXXX"),
        "ba_no": g("ba_no","nomor_ba",""),
        "customer_rank_name": customer_rank_name or name,
        "customer_no": g("customer_no","nomor_pelanggan","nrp",""),
        # gunakan alamat sebagai default jika unit/satuan tidak tersedia
        "unit": g("unit","satuan","alamat_satuan", default=g("alamat","")),
        "suspect_name": g("suspect_name","nama_tersangka",""),
        "sample_code_range": sample_code_range,
        "report_no_range": report_no_range,
        # Dasar permohonan = nomor surat (case_number)
        "request_basis": g("request_basis","dasar_permohonan","case_number","surat_permintaan_no","surat_permintaan",""),
        "surat_permintaan_no": g("surat_permintaan_no","case_number",""),
        "samples": samples,
        "submitted_by": g("submitted_by","yang_menyerahkan",""),
        "submitted_by_name": g("submitted_by_name",""),
        "submitted_by_title": g("submitted_by_title",""),
        "received_by": g("received_by","yang_menerima",""),
    "handover_datetime": g("handover_datetime","tanggal_penyerahan","received_date",""),
        # printed_at: prefer dari API; fallback ke waktu sekarang (WIB)
        "printed_at": g("printed_at","dicetak_pada","source_printed_at", fmt_indo(now_wib(), with_time=True)),
    }
    return ctx

def main():
    ap = argparse.ArgumentParser(description="Generate BA Penyerahan (Ringkasan 1 halaman)")
    ap.add_argument("--id", required=True, help="ID/kode permintaan, mis. REQ-2025-5848")
    ap.add_argument("--api", default="http://127.0.0.1:8000/requests", help="Base URL API backend")
    ap.add_argument("--outdir", default="output", help="Folder output")
    ap.add_argument("--logo-tribrata", default="public/assets/logo-tribrata-polri.png")
    ap.add_argument("--logo-pusdokkes", default="public/assets/logo-pusdokkes-polri.png")
    ap.add_argument("--pdf", action="store_true", help="Hasilkan PDF (WeasyPrint/Chrome/Edge)")
    ap.add_argument("--pdf-engine", choices=["auto","weasyprint","chrome","edge"], default="auto", help="Mesin pembuat PDF")
    ap.add_argument("--templates", default="templates", help="Folder template")
    ap.add_argument("--file", help="Baca payload JSON dari file lokal (override API)")
    args = ap.parse_args()

    # Sumber data: file lokal jika diberikan, jika tidak, API
    if args.file:
        data_path = Path(args.file)
        if not data_path.exists():
            print(f"[ERROR] File tidak ditemukan: {data_path}")
            sys.exit(1)
        print(f"[INFO] Membaca data dari file: {data_path}")
        payload = json.loads(data_path.read_text(encoding="utf-8"))
    else:
        base = args.api.rstrip("/")
        url_candidates = [f"{base}/{args.id}"]
        # Tambahkan fallback ke /api/requests jika user lupa prefix /api
        if "/api/" not in base and base.endswith("/requests"):
            alt_base = base[:-len("/requests")] + "/api/requests"
            url_candidates.append(f"{alt_base}/{args.id}")

        payload = None
        last_err = None
        for u in url_candidates:
            try:
                payload = fetch_json(u)
                print(f"[INFO] Ambil data dari: {u}")
                break
            except (HTTPError, URLError, TimeoutError) as e:
                last_err = e
                continue
        if payload is None:
            print(f"[ERROR] Gagal mengambil data dari API. Coba URL: {url_candidates}. Detail: {last_err}")
            sys.exit(1)
    ctx = map_payload(payload)

    # logo sebagai data URI agar dokumen self-contained
    ctx["logo_tribrata_data_uri"] = to_data_uri(args.logo_tribrata)
    ctx["logo_pusdokkes_data_uri"] = to_data_uri(args.logo_pusdokkes)

    env = Environment(loader=FileSystemLoader(args.templates), autoescape=select_autoescape())
    tpl = env.get_template("ba_penyerahan_ringkasan.html.j2")
    html = tpl.render(**ctx)

    Path(args.outdir).mkdir(parents=True, exist_ok=True)
    out_html = Path(args.outdir) / f"BA_Penyerahan_Ringkasan_{ctx['req_no']}.html"
    out_html.write_text(html, encoding="utf-8")
    print(f"[OK] HTML: {out_html}")

    if args.pdf:
        out_pdf = out_html.with_suffix(".pdf")
        pdf_ok = False

        def try_weasyprint():
            try:
                import weasyprint  # type: ignore
                weasyprint.HTML(string=html).write_pdf(str(out_pdf))
                print(f"[OK] PDF (WeasyPrint): {out_pdf}")
                return True
            except ImportError:
                print("[INFO] WeasyPrint tidak terinstal. Menginstal WeasyPrint...", file=sys.stderr)
                try:
                    os.system(sys.executable + " -m pip install --quiet weasyprint")
                    import weasyprint  # type: ignore
                    weasyprint.HTML(string=html).write_pdf(str(out_pdf))
                    print(f"[OK] PDF (WeasyPrint): {out_pdf}")
                    return True
                except Exception as install_err:
                    print(f"[INFO] Gagal menginstal/menggunakan WeasyPrint: {install_err}")
                    return False
            except Exception as e:
                print(f"[INFO] WeasyPrint tidak tersedia/bermasalah: {e}")
                return False

        def find_browser(prefer: str = "chrome"):
            candidates = []
            if prefer == "chrome":
                candidates.extend([
                    shutil.which("chrome"),
                    shutil.which("google-chrome"),
                    shutil.which("chrome.exe"),
                    r"C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe",
                    r"C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe",
                ])
            if prefer == "edge" or prefer == "auto":
                candidates.extend([
                    shutil.which("msedge"),
                    shutil.which("msedge.exe"),
                    r"C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe",
                    r"C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe",
                ])
            # Auto should test both
            if prefer == "auto":
                candidates = [
                    shutil.which("chrome"), shutil.which("google-chrome"), shutil.which("chrome.exe"),
                    r"C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe",
                    r"C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe",
                    shutil.which("msedge"), shutil.which("msedge.exe"),
                    r"C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe",
                    r"C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe",
                ]
            for c in candidates:
                if c and os.path.exists(c):
                    return c
            return None

        def try_chromium(browser_prefer: str = "auto"):
            exe = find_browser(browser_prefer)
            if not exe:
                print("[INFO] Chrome/Edge tidak ditemukan di sistem PATH/lokasi umum.")
                return False
            # Use file URL for Windows path
            html_url = Path(out_html).resolve().as_uri()
            out_pdf_abs = str(Path(out_pdf).resolve())
            cmd = [
                exe,
                "--headless=new",
                "--disable-gpu",
                f"--print-to-pdf={out_pdf_abs}",
                html_url,
            ]
            try:
                res = subprocess.run(cmd, capture_output=True, text=True, timeout=60)
                if res.returncode == 0 and out_pdf.exists() and out_pdf.stat().st_size > 0:
                    print(f"[OK] PDF (Chromium): {out_pdf}")
                    return True
                print(f"[INFO] Chrome/Edge gagal (rc={res.returncode}). stderr=\n{res.stderr}")
                return False
            except Exception as e:
                print(f"[INFO] Gagal menjalankan Chrome/Edge: {e}")
                return False

        # Engine selection
        if args.pdf_engine == "weasyprint":
            pdf_ok = try_weasyprint()
        elif args.pdf_engine in ("chrome", "edge"):
            pdf_ok = try_chromium(args.pdf_engine)
        else:  # auto
            pdf_ok = try_weasyprint()
            if not pdf_ok:
                pdf_ok = try_chromium("auto")

        if not pdf_ok:
            print("[WARN] Gagal membuat PDF. Instal WeasyPrint atau pastikan Chrome/Edge terpasang.")

if __name__ == "__main__":
    main()
