import hashlib
import importlib.util
import json
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location('amadis_prepare_pdf_font', ROOT / 'scripts/amadis/prepare-pdf-font.py')
prep = importlib.util.module_from_spec(spec)
spec.loader.exec_module(prep)

try:
    from fontTools.ttLib import TTFont
except ImportError:
    TTFont = None


def sha256(path):
    return hashlib.sha256(Path(path).read_bytes()).hexdigest()


class PdfFontProvenanceTests(unittest.TestCase):
    """Stdlib only: runs without FontTools."""

    def setUp(self):
        self.record = json.loads(prep.PROVENANCE.read_text(encoding='utf-8'))

    def test_source_vf_is_pinned_and_kept(self):
        self.assertEqual(sha256(prep.SOURCE), prep.SOURCE_SHA256)
        self.assertEqual(self.record['source']['sha256'], prep.SOURCE_SHA256)
        self.assertEqual(self.record['source']['upstream']['commit'], 'a85815a42757630ce188fdad368c2dfc444d4773')

    def test_licence_is_shipped_and_pinned(self):
        self.assertEqual(sha256(prep.LICENCE), prep.LICENCE_SHA256)
        self.assertIn('SIL OPEN FONT LICENSE Version 1.1', prep.LICENCE.read_text(encoding='utf-8'))
        self.assertEqual(self.record['licence']['sha256'], prep.LICENCE_SHA256)

    def test_output_hash_matches_record(self):
        self.assertEqual(sha256(prep.OUTPUT), self.record['output']['sha256'])
        self.assertEqual(prep.OUTPUT.stat().st_size, self.record['output']['bytes'])

    def test_record_declares_pinned_wght_400(self):
        self.assertEqual(self.record['parameters']['axes'], {'wght': 400})
        self.assertEqual(self.record['output']['usWeightClass'], 400)
        self.assertEqual(self.record['output']['style'], 'Regular')

    def test_record_has_no_missing_coverage(self):
        for group, data in self.record['coverage']['samples'].items():
            with self.subTest(group=group):
                self.assertEqual(data['missing'], [])

@unittest.skipIf(TTFont is None, 'FontTools not installed (use the isolated venv python)')
class PdfFontStaticInstanceTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.font = TTFont(prep.OUTPUT)
        cls.vf = TTFont(prep.SOURCE)

    def test_no_variable_tables_and_glyf_outlines(self):
        for table in prep.VARIABLE_TABLES:
            self.assertNotIn(table, self.font)
        self.assertIn('glyf', self.font)
        self.assertNotIn('CFF ', self.font)
        self.assertNotIn('CFF2', self.font)

    def test_weight_and_names_are_regular(self):
        self.assertEqual(self.font['OS/2'].usWeightClass, 400)
        self.assertTrue(self.font['OS/2'].fsSelection & 0b1000000)
        self.assertFalse(self.font['OS/2'].fsSelection & 0b100001)
        self.assertEqual(self.font['head'].macStyle, 0)
        self.assertEqual(self.font['name'].getDebugName(1), 'Noto Sans SC')
        self.assertEqual(self.font['name'].getDebugName(2), 'Regular')
        self.assertEqual(self.font['name'].getDebugName(4), 'Noto Sans SC Regular')
        self.assertEqual(self.font['name'].getDebugName(6), 'NotoSansSC-Regular')
        self.assertNotIn('Thin', self.font['name'].getDebugName(4))

    def test_cmap_is_same_as_variable_source(self):
        self.assertEqual(self.font.getBestCmap(), self.vf.getBestCmap())

    def test_required_coverage(self):
        self.assertEqual(prep.check(self.font), [])
        cmap = self.font.getBestCmap()
        for ch in '0123456789éÉàçüñ中文发票，。()-.,':
            with self.subTest(char=ch):
                self.assertIn(ord(ch), cmap)

    def test_regular_is_not_the_default_thin_master(self):
        gid_vf = self.vf.getBestCmap()[ord('中')]
        self.assertNotEqual(self.vf['glyf'][gid_vf].coordinates.array, self.font['glyf'][gid_vf].coordinates.array)

    def test_check_rejects_variable_font(self):
        problems = prep.check(self.vf)
        self.assertTrue(any('fvar' in p for p in problems))
        self.assertTrue(any('usWeightClass' in p for p in problems))


if __name__ == '__main__':
    unittest.main()
