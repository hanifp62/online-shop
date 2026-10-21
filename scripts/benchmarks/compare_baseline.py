#!/usr/bin/env python3
"""Bandingkan dua snapshot baseline ZAFIRA (default: tertua vs terbaru di docs/05-quality/baselines/).

Pemakaian:
  python3 scripts/benchmarks/compare_baseline.py                 # tertua vs terbaru
  python3 scripts/benchmarks/compare_baseline.py LAMA.txt BARU.txt
Keluar dengan kode 1 bila ada regresi (test gagal bertambah atau jumlah test berkurang).
"""
import glob
import re
import sys

NUMERIC = ["routes", "migrations", "php_files", "views", "test_files", "build_assets_bytes"]


def parse(path):
    data = {}
    for line in open(path, encoding="utf-8"):
        if ":" not in line or line.startswith("#"):
            continue
        key, _, value = line.partition(":")
        data[key.strip()] = value.strip()
    php = data.get("phpunit", "")
    for name, pattern in [("tests", r"(\d+) tests"), ("assertions", r"(\d+) assertions"),
                          ("failures", r"failures=(\d+)"), ("errors", r"errors=(\d+)"),
                          ("skipped", r"skipped=(\d+)"), ("time_s", r"time=([\d.]+)s")]:
        m = re.search(pattern, php)
        data["phpunit_" + name] = m.group(1) if m else None
    node = data.get("node", "")
    for name in ("tests", "pass", "fail"):
        m = re.search(rf"ℹ {name} (\d+)", node)
        data["node_" + name] = m.group(1) if m else None
    return data


def num(v):
    try:
        return float(v)
    except (TypeError, ValueError):
        return None


def main(argv):
    if len(argv) == 3:
        old_path, new_path = argv[1], argv[2]
    else:
        snaps = sorted(glob.glob("docs/05-quality/baselines/baseline_*.txt"))
        if len(snaps) < 2:
            print("Butuh minimal 2 snapshot; jalankan capture_baseline.sh lagi setelah perubahan.")
            return 0 if snaps else 1
        old_path, new_path = snaps[0], snaps[-1]

    old, new = parse(old_path), parse(new_path)
    keys = NUMERIC + [k for k in new if k.startswith(("phpunit_", "node_"))]
    print(f"Baseline : {old_path} ({old.get('Generated', '?')}, {old.get('Commit', '?')})")
    print(f"Terkini  : {new_path} ({new.get('Generated', '?')}, {new.get('Commit', '?')})\n")
    print(f"{'Indikator':<22}{'Baseline':>14}{'Terkini':>14}{'Delta':>12}")
    for k in keys:
        a, b = num(old.get(k)), num(new.get(k))
        delta = "" if a is None or b is None else f"{b - a:+g}"
        print(f"{k:<22}{str(old.get(k)):>14}{str(new.get(k)):>14}{delta:>12}")

    # Regresi: kegagalan bertambah, atau jumlah test turun (test dihapus diam-diam).
    regress = []
    for k in ("phpunit_failures", "phpunit_errors", "node_fail"):
        if (num(new.get(k)) or 0) > (num(old.get(k)) or 0):
            regress.append(k)
    for k in ("phpunit_tests", "node_tests"):
        a, b = num(old.get(k)), num(new.get(k))
        if a is not None and b is not None and b < a:
            regress.append(k)
    print("\nStatus:", "REGRESI pada " + ", ".join(regress) if regress else "tanpa regresi")
    return 1 if regress else 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))
