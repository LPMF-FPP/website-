import argparse, base64, json, os, sys
from datetime import datetime
from pathlib import Path

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

def main():
    parser = argparse.ArgumentParser(description="Generate Berita Acara Penerimaan HTML")
    parser.add_argument("--data", required=True, help="Path to JSON data file")
    parser.add_argument("--outdir", required=True, help="Output directory")
    parser.add_argument("--template", required=True, help="Path to Jinja2 template")
    parser.add_argument("--logo-tribrata", help="Path to logo Tribrata Polri")
    parser.add_argument("--logo-pusdokkes", help="Path to logo Pusdokkes Polri")
    args = parser.parse_args()

    # Load data
    with open(args.data, "r", encoding="utf-8") as f:
        data = json.load(f)

    # Prepare context for template
    context = {
        "request_id": data.get("request_id", ""),
        "request_no": data.get("request_no", ""),
        "surat_permintaan_no": data.get("surat_permintaan_no", ""),
        "received_date": data.get("received_date", ""),
        "customer_rank_name": data.get("customer_rank_name", ""),
        "customer_no": data.get("customer_no", ""),
        "unit": data.get("unit", ""),
        "addressed_to": data.get("addressed_to", ""),
        "tests_summary": data.get("tests_summary", ""),
        "sample_count": data.get("sample_count", 0),
        "samples": data.get("samples", []),
        "submitted_by": data.get("submitted_by", ""),
        "received_by": data.get("received_by", ""),
        "source_printed_at": data.get("source_printed_at", ""),
        "generated_at": datetime.now().strftime("%d %B %Y %H:%M:%S"),
    }

    # Add logo data URIs
    if args.logo_tribrata:
        context["logo_tribrata_data_uri"] = to_data_uri(args.logo_tribrata)
    else:
        context["logo_tribrata_data_uri"] = to_data_uri("")
        
    if args.logo_pusdokkes:
        context["logo_pusdokkes_data_uri"] = to_data_uri(args.logo_pusdokkes)
    else:
        context["logo_pusdokkes_data_uri"] = to_data_uri("")

    # Setup Jinja2
    template_dir = os.path.dirname(args.template)
    template_name = os.path.basename(args.template)
    
    env = Environment(
        loader=FileSystemLoader(template_dir),
        autoescape=select_autoescape(['html', 'xml'])
    )
    template = env.get_template(template_name)

    # Render
    html_output = template.render(**context)

    # Write output
    os.makedirs(args.outdir, exist_ok=True)
    output_filename = f"Berita_Acara_Penerimaan_{data.get('request_no', 'unknown')}_ID-{data.get('request_id', 'unknown')}.html"
    output_path = os.path.join(args.outdir, output_filename)
    
    with open(output_path, "w", encoding="utf-8") as f:
        f.write(html_output)
    
    print(f"Generated: {output_path}", file=sys.stderr)
    print(output_path)  # Output path for caller

if __name__ == "__main__":
    main()
