import contextlib
import importlib.util
import io
import json
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch


SPEC = importlib.util.spec_from_file_location(
    'docker_stage_boundary_root',
    Path(__file__).resolve().parents[2] / 'scripts/amadis/docker_stage.py',
)
stage = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(stage)


class BoundaryRootTests(unittest.TestCase):
    def test_boundary_callers_generate_guard_compatible_root_and_short_stage_suffix(self):
        for command in ('boundary-preflight', 'stage-runtime', 'stage-install'):
            with self.subTest(command=command), tempfile.TemporaryDirectory() as directory:
                base = Path(directory)
                short_suffix = '01' * 6
                root_suffix = '01' * 12
                with patch.object(stage.launch, 'BASE', base), \
                     patch.object(stage.launch, 'REPO', base), \
                     patch.object(stage.os, 'urandom', side_effect=lambda size: b'\x01' * size), \
                     patch.object(stage, 'copy_source', side_effect=RuntimeError('stop before source copy')), \
                     patch.object(stage.subprocess, 'run') as run, \
                     contextlib.redirect_stdout(io.StringIO()):
                    self.assertEqual(stage.main([command]), 1)
                run.assert_not_called()
                evidence_kind = 'stage-runtime' if command == 'stage-runtime' else 'pdf-stage'
                report_path = (base / 'docs/execution/evidence/2026-10-13'
                               / evidence_kind / short_suffix / 'report.json')
                report = json.loads(report_path.read_text())
                root = Path(report['root'])
                self.assertRegex(root.name, r'^amadis-[0-9a-f]{24}$')
                self.assertEqual(root, base / ('amadis-' + root_suffix))
                self.assertTrue(root.is_dir())
                self.assertEqual(Path(report['stage']), base / ('amadis-stage-' + short_suffix))
                self.assertEqual(report['error'], 'stop before source copy')


if __name__ == '__main__':
    unittest.main()
