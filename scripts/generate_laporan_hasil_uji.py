#!/usr/bin/env python3
"""
Generator Laporan Hasil Uji (LHU) untuk Pusdokkes Polri
Menggunakan Jinja2 untuk templating dan optional WeasyPrint untuk PDF
"""

import argparse
import base64
import json
import os
import sys
from datetime import datetime
from pathlib import Path

def install_package(package_name):
    """Install package jika belum tersedia"""
    try:
        __import__(package_name)
        return True
    except ImportError:
        print(f"[INFO] Menginstal {package_name}...", file=sys.stderr)
        import subprocess
        try:
            subprocess.check_call([sys.executable, "-m", "pip", "install", "--quiet", package_name])
            print(f"[OK] {package_name} berhasil diinstall", file=sys.stderr)
            return True
        except Exception as e:
            print(f"[ERROR] Gagal install {package_name}: {e}", file=sys.stderr)
            return False

# Install dependencies jika perlu
if not install_package('jinja2'):
    sys.exit(1)
if not install_package('requests'):
    sys.exit(1)

from jinja2 import Environment, FileSystemLoader, select_autoescape
import requests

def to_data_uri(path):
    """Convert file gambar ke data URI untuk embed di HTML"""
    if not os.path.exists(path):
        print(f"[WARN] File tidak ditemukan: {path}", file=sys.stderr)
        return ""
    
    with open(path, "rb") as f:
        b64 = base64.b64encode(f.read()).decode("ascii")
    
    ext = os.path.splitext(path)[1].lower().lstrip(".")
    mime_map = {
        "png": "image/png",
        "jpg": "image/jpeg",
        "jpeg": "image/jpeg",
        "gif": "image/gif",
        "svg": "image/svg+xml",
    }
    mime = mime_map.get(ext, "application/octet-stream")
    
    return f"data:{mime};base64,{b64}"

def fetch_api_data(api_url, process_id):
    """Fetch data dari Laravel API"""
    url = f"{api_url}/{process_id}"
    print(f"[INFO] Fetching data dari {url}...", file=sys.stderr)
    
    try:
        resp = requests.get(url, timeout=10)
        resp.raise_for_status()
        data = resp.json()
        print(f"[OK] Data berhasil diambil", file=sys.stderr)
        return data
    except requests.RequestException as e:
        print(f"[ERROR] Gagal fetch API: {e}", file=sys.stderr)
        sys.exit(1)

def generate_html(data, template_path, logo_pusdokkes_path, logo_tribrata_path):
    """Generate HTML dari template Jinja2"""
    env = Environment(
        loader=FileSystemLoader(os.path.dirname(template_path)),
        autoescape=select_autoescape(["html", "xml"]),
    )
    
    # Custom filters
    env.filters["to_data_uri"] = to_data_uri
    
    template = env.get_template(os.path.basename(template_path))
    
    # Embed logos
    logo_pusdokkes_uri = to_data_uri(logo_pusdokkes_path) if logo_pusdokkes_path else ""
    logo_tribrata_uri = to_data_uri(logo_tribrata_path) if logo_tribrata_path else ""
    
    # Render template
    html = template.render(
        **data,
        logo_pusdokkes=logo_pusdokkes_uri,
        logo_tribrata=logo_tribrata_uri,
        generated_timestamp=datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    )
    
    return html

def generate_pdf(html_content, output_path):
    """Generate PDF dari HTML menggunakan WeasyPrint"""
    try:
        from weasyprint import HTML
        print(f"[INFO] Generating PDF...", file=sys.stderr)
        HTML(string=html_content).write_pdf(output_path)
        print(f"[OK] PDF saved: {output_path}", file=sys.stderr)
        return True
    except ImportError:
        print("[WARN] WeasyPrint tidak terinstall. Lewati PDF generation.", file=sys.stderr)
        print("[INFO] Install dengan: pip install weasyprint", file=sys.stderr)
        return False
    except Exception as e:
        print(f"[ERROR] Gagal generate PDF: {e}", file=sys.stderr)
        return False

def main():
    parser = argparse.ArgumentParser(description="Generate Laporan Hasil Uji")
    parser.add_argument("--id", help="Sample Process ID (for backward compatibility)")
    parser.add_argument("--api", help="API URL (deprecated, use --data instead)")
    parser.add_argument("--data", help="Path to JSON data file")
    parser.add_argument("--template", help="Path ke template Jinja2 (default: templates/laporan_hasil_uji.html.j2)")
    parser.add_argument("--outdir", default="output/laporan-hasil-uji", help="Output directory")
    parser.add_argument("--pdf", action="store_true", help="Generate PDF selain HTML")
    parser.add_argument("--logo-pusdokkes", help="Path ke logo Pusdokkes PNG")
    parser.add_argument("--logo-tribrata", help="Path ke logo Tribrata PNG")
    
    args = parser.parse_args()
    
    # Resolve paths
    script_dir = Path(__file__).parent.resolve()
    project_root = script_dir.parent
    
    template_path = args.template or project_root / "templates" / "laporan_hasil_uji.html.j2"
    logo_pusdokkes = args.logo_pusdokkes or project_root / "public" / "images" / "logo-pusdokkes-polri.png"
    logo_tribrata = args.logo_tribrata or project_root / "public" / "images" / "logo-tribrata-polri.png"
    output_dir = Path(args.outdir) if Path(args.outdir).is_absolute() else project_root / args.outdir
    
    # Validasi
    if not template_path.exists():
        print(f"[ERROR] Template tidak ditemukan: {template_path}", file=sys.stderr)
        sys.exit(1)
    
    # Buat output directory
    output_dir.mkdir(parents=True, exist_ok=True)
    
    # Get data - prioritize JSON file over API
    if args.data:
        print(f"[INFO] Loading data from file: {args.data}", file=sys.stderr)
        with open(args.data, 'r', encoding='utf-8') as f:
            data = json.load(f)
    elif args.api and args.id:
        print(f"[WARN] Using deprecated API method. Consider using --data instead.", file=sys.stderr)
        data = fetch_api_data(args.api, args.id)
    else:
        print(f"[ERROR] Either --data or (--api and --id) must be provided", file=sys.stderr)
        sys.exit(1)
    
    # Generate HTML
    print(f"[INFO] Generating HTML...", file=sys.stderr)
    html = generate_html(data, template_path, logo_pusdokkes, logo_tribrata)
    
    # Save HTML
    report_number = data.get("report_number", args.id)
    html_filename = f"Laporan_Hasil_Uji_{report_number}.html"
    html_path = output_dir / html_filename
    
    html_path.write_text(html, encoding="utf-8")
    print(f"[OK] HTML saved: {html_path}", file=sys.stderr)
    
    # Generate PDF jika diminta
    if args.pdf:
        pdf_filename = f"Laporan_Hasil_Uji_{report_number}.pdf"
        pdf_path = output_dir / pdf_filename
        generate_pdf(html, str(pdf_path))
    
    # Output JSON untuk Laravel
    result = {
        "success": True,
        "html_path": str(html_path.relative_to(project_root)),
        "html_filename": html_filename,
        "report_number": report_number
    }
    
    if args.pdf and (output_dir / pdf_filename).exists():
        result["pdf_path"] = str((output_dir / pdf_filename).relative_to(project_root))
        result["pdf_filename"] = pdf_filename
    
    print(json.dumps(result))

if __name__ == "__main__":
    main()
