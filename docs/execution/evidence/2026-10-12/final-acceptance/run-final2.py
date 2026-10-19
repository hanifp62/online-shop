import json
import pathlib
import subprocess

out = pathlib.Path(__file__).resolve().parent
root = out.parents[4]
commands = {
    'php-standard-final2': ['php', 'artisan', 'test'],
    'php-random-final2': ['php', 'artisan', 'test', '--order-by=random', '--random-order-seed=20261012'],
    'pint-final2': ['vendor/bin/pint', '--test'],
    'build-final2': ['npm', 'run', 'build'],
    'node-tests-final2': ['sh', '-c', 'node --test tests/*.mjs'],
    'php-syntax-final2': ['sh', '-c', "find app routes tests docs/execution/evidence/2026-10-11/phase4/security-run11 -name '*.php' -print0 | xargs -0 -n1 php -l"],
    'js-syntax-final2': ['sh', '-c', "find resources/js public/prototype public/js -name '*.js' -print0 | xargs -0 -n1 node --check"],
}
for original, target in [('sweep-14', 'sweep-15-final2'), ('header-breakpoint', 'header-breakpoint-final2'), ('extra-browser', 'extra-browser-final2'), ('overlay-run14', 'overlay-final2'), ('drawer-interactions-run14', 'drawer-interactions-final2'), ('hit-collision-run14', 'hit-collision-final2'), ('contrast-run14', 'contrast-final2')]:
    source = (out / (original + '.mjs')).read_text()
    source = source.replace(original, target)
    if original == 'sweep-14':
        source = source.replace('shots-sweep-14', 'shots-sweep-15-final2')
    (out / (target + '.mjs')).write_text(source)
    commands[target] = ['node', str(out / (target + '.mjs'))]
commands['dark-contrast-final2'] = ['node', str(out / 'dark-contrast-final2.mjs')]
commands['diff-check-final2'] = ['git', 'diff', '--check']
results = []
for name, command in commands.items():
    (out / (name + '.cmd')).write_text(subprocess.list2cmdline(command) + '\n')
    with (out / (name + '.log')).open('w') as stream:
        result = subprocess.run(command, cwd=root, stdout=stream, stderr=subprocess.STDOUT)
    (out / (name + '.exitcode')).write_text(str(result.returncode) + '\n')
    results.append({'name': name, 'exitcode': result.returncode})
    print(name, result.returncode, flush=True)
    (out / 'regression-results-final2.json').write_text(json.dumps(results, indent=2) + '\n')
