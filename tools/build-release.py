#!/usr/bin/env python3
"""Build a reproducible installation ZIP containing only distributable files."""

import argparse
import hashlib
import re
import zipfile
from pathlib import Path


def main():
    source = Path(__file__).resolve().parents[1]
    slug = "stock-threshold-override-for-woocommerce"
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--output", type=Path, default=source / "dist")
    args = parser.parse_args()

    entrypoint = source / f"{slug}.php"
    header = entrypoint.read_text(encoding="utf-8")
    readme = (source / "readme.txt").read_text(encoding="utf-8")
    version = re.search(r"^ \* Version: (\d+\.\d+\.\d+)$", header, re.MULTILINE)
    if not version or f"Stable tag: {version[1]}\n" not in readme:
        raise SystemExit("Plugin version and readme stable tag must match.")
    plugin_name = re.search(r"^ \* Plugin Name: (.+)$", header, re.MULTILINE)
    if not plugin_name or not readme.startswith(f"=== {plugin_name[1]} ===\n"):
        raise SystemExit("Plugin name must match the readme title.")

    files = [entrypoint, source / "readme.txt", source / "LICENSE"]
    for directory in ("includes", "languages"):
        files.extend(path for path in (source / directory).rglob("*") if path.is_file())
    if not all(path.is_file() for path in files):
        raise SystemExit("A required release file is missing.")

    output = args.output.resolve()
    output.mkdir(parents=True, exist_ok=True)
    archive = output / f"{slug}-{version[1]}.zip"
    with zipfile.ZipFile(archive, "w", compression=zipfile.ZIP_DEFLATED) as package:
        for path in sorted(files):
            relative = path.relative_to(source)
            if path.is_symlink() or any(part.startswith(".") for part in relative.parts):
                raise SystemExit(f"Unexpected release file: {relative}")
            info = zipfile.ZipInfo(f"{slug}/{relative.as_posix()}", (1980, 1, 1, 0, 0, 0))
            info.external_attr = 0o100644 << 16
            info.compress_type = zipfile.ZIP_DEFLATED
            package.writestr(info, path.read_bytes())

    digest = hashlib.sha256(archive.read_bytes()).hexdigest()
    archive.with_suffix(".zip.sha256").write_text(f"{digest}  {archive.name}\n", encoding="utf-8")
    print(archive)
    print(f"{len(files)} files, {archive.stat().st_size} bytes")
    print(f"SHA-256: {digest}")


if __name__ == "__main__":
    main()
