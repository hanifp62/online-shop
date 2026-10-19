#!/usr/bin/env python3
"""Build the static Noto Sans SC Regular font used for PDF embedding.

Source: google/fonts ofl/notosanssc/NotoSansSC[wght].ttf, pinned to commit
a85815a42757630ce188fdad368c2dfc444d4773, stored as resources/fonts/NotoSansSC-VF.ttf.
Output: resources/fonts/NotoSansSC-Regular.ttf, a static glyf TrueType instance at
wght=400 (the only axis), plus resources/fonts/NotoSansSC-Regular.provenance.json.

Requires FontTools in an isolated venv, for example:
    <venv>/bin/python scripts/amadis/prepare-pdf-font.py build
    <venv>/bin/python scripts/amadis/prepare-pdf-font.py verify
"""
import argparse
import hashlib
import json
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
FONT_DIR = ROOT / 'resources/fonts'
SOURCE = FONT_DIR / 'NotoSansSC-VF.ttf'
OUTPUT = FONT_DIR / 'NotoSansSC-Regular.ttf'
PROVENANCE = FONT_DIR / 'NotoSansSC-Regular.provenance.json'
LICENCE = FONT_DIR / 'OFL.txt'

SOURCE_SHA256 = 'a3041811a78c361b1de50f953c805e0244951c21c5bd412f7232ef0d899af0da'
LICENCE_SHA256 = '1c05c68c34f9708415aada51f17e1b0092d2cea709bf4a94cd38114f9e73d7d9'
UPSTREAM = {
    'repository': 'https://github.com/google/fonts',
    'commit': 'a85815a42757630ce188fdad368c2dfc444d4773',
    'path': 'ofl/notosanssc/NotoSansSC[wght].ttf',
    'git_blob_sha1': 'fb0637bafbcd804fe32152370a1225990745b4bc',
    'licence_path': 'ofl/notosanssc/OFL.txt',
    'licence_git_blob_sha1': '1c9f43281b8f216c5461fe9ac729afbade7724e4',
}
AXES = {'wght': 400}
FAMILY = 'Noto Sans SC'
STYLE = 'Regular'
POSTSCRIPT = 'NotoSansSC-Regular'
VARIABLE_TABLES = ('fvar', 'gvar', 'avar', 'HVAR', 'VVAR', 'MVAR', 'cvar')

# Coverage the PDFs need: CJK, Latin accents (Indonesian/French names), digits, punctuation.
COVERAGE = {
    'digits': '0123456789',
    'ascii_letters': 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz',
    'ascii_punctuation': '!"#$%&\'()*+,-./:;<=>?@[\\]^_`{|}~',
    'latin_accents': 'ÀÁÂÄÇÈÉÊËÎÏÔÖÙÛÜàáâäçèéêëîïôöùûüÿñÑœŒæÆ',
    'typographic_punctuation': '\u2013\u2014\u2018\u2019\u201c\u201d\u2026\u00b7\u00ab\u00bb€¥£',
    'chinese': '中文简体汉字店铺订单发票金额数量价格日期客户地址电话谢谢光之屋',
    'cjk_punctuation': '\u3001\u3002\uff0c\uff1a\uff1b\uff01\uff1f\uff08\uff09\u300a\u300b\u300c\u300d\uffe5',
}


def sha256(path):
    h = hashlib.sha256()
    with open(path, 'rb') as fh:
        for chunk in iter(lambda: fh.read(1 << 20), b''):
            h.update(chunk)
    return h.hexdigest()


def _fonttools():
    try:
        import fontTools
        from fontTools.ttLib import TTFont
        from fontTools.varLib import instancer
    except ImportError:
        sys.exit('FontTools missing: run with an isolated venv python that has fonttools installed')
    return fontTools, TTFont, instancer


def coverage(font):
    cmap = font.getBestCmap()
    report = {}
    for name, chars in COVERAGE.items():
        missing = [f'U+{ord(c):04X}' for c in chars if ord(c) not in cmap]
        report[name] = {'checked': len(chars), 'missing': missing}
    cjk = sum(1 for cp in cmap if 0x4E00 <= cp <= 0x9FFF)
    return report, len(cmap), cjk


def _set_names(font):
    name = font['name']
    full = f'{FAMILY} {STYLE}'
    version = name.getDebugName(5) or ''
    unique = f'{version.split(";")[0]};ADBO;{POSTSCRIPT};ADOBE' if version else POSTSCRIPT
    for rec in list(name.names):
        if rec.nameID in (16, 17, 25):
            name.removeNames(nameID=rec.nameID)
    for rec in name.names:
        values = {1: FAMILY, 2: STYLE, 3: unique, 4: full, 6: POSTSCRIPT}
        if rec.nameID in values:
            rec.string = values[rec.nameID]
    os2 = font['OS/2']
    os2.usWeightClass = 400
    os2.fsSelection = (os2.fsSelection & ~0b100001) | 0b1000000  # clear italic/bold, set REGULAR
    font['head'].macStyle = 0


def check(font):
    """Return a list of problems with the static instance; empty means valid."""
    problems = [f'variable table present: {t}' for t in VARIABLE_TABLES if t in font]
    if 'glyf' not in font or 'CFF ' in font or 'CFF2' in font:
        problems.append('not a glyf TrueType outline font')
    if font['OS/2'].usWeightClass != 400:
        problems.append(f'usWeightClass {font["OS/2"].usWeightClass} != 400')
    if not font['OS/2'].fsSelection & 0b1000000 or font['OS/2'].fsSelection & 0b100001:
        problems.append('fsSelection is not REGULAR')
    expected = {1: FAMILY, 2: STYLE, 4: f'{FAMILY} {STYLE}', 6: POSTSCRIPT}
    for nid, value in expected.items():
        if font['name'].getDebugName(nid) != value:
            problems.append(f'name {nid} = {font["name"].getDebugName(nid)!r}, expected {value!r}')
    if 'Source' in (font['name'].getDebugName(1) or ''):
        problems.append('reserved font name "Source" used as primary family name')
    report, _, cjk = coverage(font)
    problems += [f'{k} missing {v["missing"]}' for k, v in report.items() if v['missing']]
    if cjk < 20000:
        problems.append(f'CJK unified coverage too small: {cjk}')
    return problems


def build():
    fontTools, TTFont, instancer = _fonttools()
    if sha256(SOURCE) != SOURCE_SHA256:
        sys.exit(f'source hash mismatch: {SOURCE}')
    if sha256(LICENCE) != LICENCE_SHA256:
        sys.exit(f'licence hash mismatch: {LICENCE}')
    vf = TTFont(SOURCE, recalcTimestamp=False)
    if [a.axisTag for a in vf['fvar'].axes] != list(AXES):
        sys.exit('unexpected axes; refusing to pin a partial instance')
    font = instancer.instantiateVariableFont(vf, AXES, static=True)
    font.recalcTimestamp = False  # keep head.modified from source so the output is reproducible
    _set_names(font)
    tmp = OUTPUT.with_suffix('.ttf.tmp')
    font.save(tmp)
    problems = check(TTFont(tmp))
    if problems:
        tmp.unlink()
        sys.exit('static instance invalid:\n' + '\n'.join(problems))
    tmp.replace(OUTPUT)
    out = TTFont(OUTPUT)
    report, cmap_size, cjk = coverage(out)
    record = {
        'output': {'file': OUTPUT.name, 'sha256': sha256(OUTPUT), 'bytes': OUTPUT.stat().st_size,
                   'format': 'static TrueType (glyf), no fvar/gvar/avar/HVAR',
                   'family': FAMILY, 'style': STYLE, 'postscript_name': POSTSCRIPT,
                   'usWeightClass': 400, 'glyphs': out['maxp'].numGlyphs},
        'source': {'file': SOURCE.name, 'sha256': SOURCE_SHA256, 'upstream': UPSTREAM},
        'tool': {'name': 'fontTools.varLib.instancer.instantiateVariableFont',
                 'fonttools_version': fontTools.version,
                 'python': sys.version.split()[0],
                 'script': 'scripts/amadis/prepare-pdf-font.py'},
        'parameters': {'axes': AXES, 'static': True, 'recalcTimestamp': False,
                       'name_table': 'family/style/full/postscript set to Regular; IDs 16, 17, 25 removed'},
        'licence': {'file': LICENCE.name, 'sha256': LICENCE_SHA256, 'name': 'SIL Open Font License 1.1',
                    'copyright': 'Copyright 2014-2021 Adobe (http://www.adobe.com/), with Reserved Font Name \'Source\'',
                    'note': 'Modified Version under OFL 1.1; ship OFL.txt with the font; primary name does not use "Source".'},
        'coverage': {'cmap_codepoints': cmap_size, 'cjk_unified_U4E00_U9FFF': cjk, 'samples': report},
    }
    PROVENANCE.write_text(json.dumps(record, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    print(f'wrote {OUTPUT.relative_to(ROOT)} sha256={record["output"]["sha256"]}')
    return 0


def verify():
    _, TTFont, _ = _fonttools()
    record = json.loads(PROVENANCE.read_text(encoding='utf-8'))
    problems = []
    if sha256(SOURCE) != SOURCE_SHA256:
        problems.append('source hash mismatch')
    if sha256(LICENCE) != LICENCE_SHA256:
        problems.append('licence hash mismatch')
    if sha256(OUTPUT) != record['output']['sha256']:
        problems.append('output hash differs from provenance record')
    problems += check(TTFont(OUTPUT))
    if problems:
        print('\n'.join(problems), file=sys.stderr)
        return 1
    print(f'ok {OUTPUT.relative_to(ROOT)} sha256={record["output"]["sha256"]}')
    return 0


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    parser.add_argument('command', choices=('build', 'verify'))
    args = parser.parse_args(argv)
    return build() if args.command == 'build' else verify()


if __name__ == '__main__':
    sys.exit(main())
