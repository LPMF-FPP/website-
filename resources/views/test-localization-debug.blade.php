<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Localization Save Debug</title>
    <script>
        async function testLocalizationSave() {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
            
            const payload = {
                localization: {
                    timezone: 'Asia/Jakarta',
                    date_format: 'DD/MM/YYYY',
                    number_format: '1.234,56',
                    language: 'id'
                },
                retention: {
                    storage_driver: 'public',
                    storage_folder_path: '',
                    purge_after_days: 365,
                    export_filename_pattern: ''
                }
            };
            
            console.log('Sending:', JSON.stringify(payload, null, 2));
            
            try {
                const response = await fetch('/api/settings/localization-retention', {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(payload)
                });
                
                console.log('Status:', response.status);
                const data = await response.json();
                console.log('Response:', data);
                
                if (response.status === 422) {
                    console.error('VALIDATION ERRORS:', data.errors);
                    document.getElementById('error-output').innerHTML = '<h3>Validation Errors:</h3><pre>' + JSON.stringify(data.errors, null, 2) + '</pre>';
                } else {
                    console.log('SUCCESS!');
                    document.getElementById('error-output').innerHTML = '<h3 style="color: green">SUCCESS!</h3><pre>' + JSON.stringify(data, null, 2) + '</pre>';
                }
            } catch (error) {
                console.error('Error:', error);
                document.getElementById('error-output').innerHTML = '<h3 style="color: red">ERROR:</h3><pre>' + error.message + '</pre>';
            }
        }
        
        window.addEventListener('DOMContentLoaded', () => {
            testLocalizationSave();
        });
    </script>
</head>
<body>
    <h1>Localization Save Debug Test</h1>
    <div id="error-output">Testing...</div>
</body>
</html>
