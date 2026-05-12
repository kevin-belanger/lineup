 #!/usr/bin/env python3

import csv
import re
from pathlib import Path

PROJECT_ROOT = Path(__file__).resolve().parent

SCAN_DIRS = [
    "resources/views",
    "app/Livewire",
    "app/Http/Controllers",
    "app/Models",
    "app/Services",
    "app/Actions",
]

OUTPUT_FILE = PROJECT_ROOT / "translation-strings.csv"

FILE_EXTENSIONS = {
    ".php",
    ".js",
    ".vue",
}

PATTERNS = [
    # __('Text')
    re.compile(r"__\(\s*['\"]([^'\"]+)['\"]\s*(?:,\s*\[[^\)]*\])?\)"),

    # @lang('Text')
    re.compile(r"@lang\(\s*['\"]([^'\"]+)['\"]\s*(?:,\s*\[[^\)]*\])?\)"),

    # trans('Text')
    re.compile(r"trans\(\s*['\"]([^'\"]+)['\"]\s*(?:,\s*\[[^\)]*\])?\)"),
]


def should_scan_file(path: Path) -> bool:
    if path.name.startswith("."):
        return False

    relative = str(path.relative_to(PROJECT_ROOT))

    ignored_parts = [
        "vendor/",
        "node_modules/",
        "storage/",
        "bootstrap/cache/",
        ".git/",
        "public/build/",
    ]

    if any(part in relative for part in ignored_parts):
        return False

    if str(path).endswith(".blade.php"):
        return True

    return path.suffix in FILE_EXTENSIONS


def find_line_number(content: str, index: int) -> int:
    return content.count("\n", 0, index) + 1


def scan_file(path: Path):
    try:
        content = path.read_text(encoding="utf-8")
    except UnicodeDecodeError:
        content = path.read_text(encoding="latin-1")

    results = []

    for pattern in PATTERNS:
        for match in pattern.finditer(content):
            text = match.group(1).strip()

            if not text:
                continue

            results.append({
                "file": str(path.relative_to(PROJECT_ROOT)),
                "line": find_line_number(content, match.start()),
                "string": text,
            })

    return results


def main():
    rows = []

    for scan_dir in SCAN_DIRS:
        base = PROJECT_ROOT / scan_dir

        if not base.exists():
            continue

        for path in base.rglob("*"):
            if path.is_file() and should_scan_file(path):
                rows.extend(scan_file(path))

    # Deduplicate globally by string, but keep first location.
    seen = set()
    unique_rows = []

    for row in sorted(rows, key=lambda r: (r["string"], r["file"], r["line"])):
        if row["string"] in seen:
            continue

        seen.add(row["string"])
        unique_rows.append(row)

    with OUTPUT_FILE.open("w", newline="", encoding="utf-8") as csvfile:
        writer = csv.DictWriter(
            csvfile,
            fieldnames=["string", "file", "line"],
        )
        writer.writeheader()
        writer.writerows(unique_rows)

    print(f"Done. Found {len(unique_rows)} unique translation strings.")
    print(f"Output: {OUTPUT_FILE.relative_to(PROJECT_ROOT)}")


if __name__ == "__main__":
    main()