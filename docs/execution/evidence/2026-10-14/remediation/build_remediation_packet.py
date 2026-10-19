#!/usr/bin/env python3
"""Build and verify the allowlisted ZAFIRA remediation evidence packet."""
from __future__ import annotations

import hashlib
import os
import re
import subprocess
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[5]
OUT = ROOT / "REMEDIATION_PACKET.zip"
EVIDENCE = Path("docs/execution/evidence/2026-10-14/remediation")
ASSESS = Path("docs/execution/evidence/2026-10-14/architecture-assessment")

ALLOW = [
    EVIDENCE / "REMEDIATION_REPORT.md",
    *[EVIDENCE / "final" / p.name for p in (ROOT / EVIDENCE / "final").iterdir() if p.is_file()],
    *[EVIDENCE / "native" / p.name for p in (ROOT / EVIDENCE / "native").iterdir() if p.is_file()],
    *[EVIDENCE / "pdf" / p.relative_to(ROOT / EVIDENCE / "pdf") for p in (ROOT / EVIDENCE / "pdf").rglob("*") if p.is_file()],
    *[EVIDENCE / "concurrency" / p.name for p in (ROOT / EVIDENCE / "concurrency").iterdir() if p.is_file()],
    Path("docs/03-architecture/ARCHITECTURE_DECISIONS.md"), Path("docs/MANUAL_TESTING.md"),
    *[ASSESS / name for name in ("GAP_MATRIX.csv", "TEST_RESULTS.md", "AUDIT_HANDOFF.md", "ARCHITECTURE_AS_IS.md", "evidence/public-benchmarks.json")],
    Path("docs/02-product/REQUIREMENTS_TRACEABILITY.md"),
    *[Path("scripts/manual") / name for name in ("setup.sh", "preflight.py", "fixture.php", "acceptance.py", "start-server.sh")],
    Path("tests/Concurrency/checkout-sqlite.php"), Path("tests/cart-concurrency.test.mjs"),
]

SECRET_PATTERNS = [
    re.compile(rb"APP_KEY\s*=\s*base64:[A-Za-z0-9+/=]{20,}", re.I),
    # Literal values only; code that generates random runtime passwords is allowed.
    re.compile(rb"(?:DB_PASSWORD|DATABASE_PASSWORD)\s*=\s*[^\s$]{3,}", re.I),
    re.compile(rb"password['\"]?\s*(?:=>|=|:)\s*['\"][^'\"\s]{6,}['\"]", re.I),
    re.compile(rb"(?:api[_-]?key|access[_-]?token|refresh[_-]?token|session[_-]?token)\s*[=:]\s*['\"]?[A-Za-z0-9_./+=-]{16,}", re.I),
    re.compile(rb"-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----"),
    re.compile(rb"(?:gh[pousr]_[A-Za-z0-9]{30,}|sk-[A-Za-z0-9_-]{24,})"),
]


def digest(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def main() -> None:
    if OUT.exists():
        raise SystemExit(f"Refusing to overwrite existing packet: {OUT}")
    files: dict[str, bytes] = {}
    for rel in sorted(set(ALLOW), key=str):
        src = ROOT / rel
        if not src.is_file():
            raise SystemExit(f"Allowlisted file missing: {rel}")
        name = rel.as_posix()
        if any(part in {"vendor", "node_modules", ".git", "sessions"} for part in rel.parts) or rel.name.startswith(".env") or rel.suffix.lower() in {".sqlite", ".db"} or name.endswith("AUDIT_PACKET.zip"):
            raise SystemExit(f"Forbidden allowlist member: {name}")
        data = src.read_bytes()
        if not data.startswith(b"\x89PNG") and not data.startswith(b"%PDF"):
            for pattern in SECRET_PATTERNS:
                if pattern.search(data):
                    raise SystemExit(f"Secret-like content found in {name}; packet not built")
        files[name] = data
    manifest = "".join(f"{digest(data)}  {name}\n" for name, data in sorted(files.items())).encode()
    files["MANIFEST.sha256"] = manifest
    with zipfile.ZipFile(OUT, "x", compression=zipfile.ZIP_DEFLATED) as zf:
        for name, data in sorted(files.items()):
            zf.writestr(name, data)
    subprocess.run(["unzip", "-t", str(OUT)], check=True, stdout=subprocess.PIPE, stderr=subprocess.STDOUT)
    with zipfile.ZipFile(OUT) as zf:
        zf.extractall(OUT.parent / (OUT.stem + ".verify"))
    verify = OUT.parent / (OUT.stem + ".verify")
    try:
        for name, data in files.items():
            actual = (verify / name).read_bytes()
            if actual != data:
                raise SystemExit(f"Extracted content mismatch: {name}")
        for line in manifest.decode().splitlines():
            expected, name = line.split("  ", 1)
            if digest((verify / name).read_bytes()) != expected:
                raise SystemExit(f"Manifest hash mismatch: {name}")
    finally:
        import shutil
        shutil.rmtree(verify)
    print(f"ZIP_SHA256={digest(OUT.read_bytes())}")
    print(f"SIZE_BYTES={OUT.stat().st_size}")
    print(f"MEMBER_COUNT={len(files)}")
    print(f"MANIFEST_SHA256={digest(manifest)}")

if __name__ == "__main__":
    main()
