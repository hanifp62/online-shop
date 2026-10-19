#!/usr/bin/env python3
"""Record subprocess evidence without shell pipelines or credential output."""
import datetime
import json
import os
from pathlib import Path
import subprocess
import sys

OUT = Path(__file__).resolve().parent

def run(name, command, cwd, env=None):
    start = datetime.datetime.now(datetime.timezone.utc).isoformat()
    result = subprocess.run(command, cwd=cwd, env=env, capture_output=True)
    (OUT / (name + '.stdout.txt')).write_bytes(result.stdout)
    (OUT / (name + '.stderr.txt')).write_bytes(result.stderr)
    record = dict(command=command, cwd=str(cwd), utc_start=start,
                  utc_end=datetime.datetime.now(datetime.timezone.utc).isoformat(), returncode=result.returncode)
    (OUT / (name + '.json')).write_text(json.dumps(record, indent=2) + '\n')
    print(name, 'returncode=', result.returncode)
    return result.returncode

if __name__ == '__main__':
    sys.exit(run(sys.argv[1], sys.argv[3:], sys.argv[2], os.environ.copy()))
