import json
import pathlib
import subprocess

root = pathlib.Path(__file__).resolve().parents[5]
out = pathlib.Path(__file__).resolve().parent
commands = {
    'php-standard': ['php', 'artisan', 'test'],
    'php-random': ['php', 'artisan', 'test', '--order-by=random', '--random-order-seed=20261012'],
    'pint-initial': ['vendor/bin/pint', '--test'],
    'build-final': ['npm', 'run', 'build'],
    'node-tests': ['sh', '-c', 'node --test tests/*.mjs'],
    'php-syntax': ['sh', '-c', "find app routes tests -name '*.php' -print0 | xargs -0 -n1 php -l"],
    'js-syntax': ['sh', '-c', "find resources/js public/prototype -name '*.js' -print0 | xargs -0 -n1 node --check"],
    'diff-check': ['git', 'diff', '--check'],
}
results = []
for name, command in commands.items():
    (out / (name + '.cmd')).write_text(subprocess.list2cmdline(command) + '\n')
    with (out / (name + '.log')).open('w') as stream:
        result = subprocess.run(command, cwd=root, stdout=stream, stderr=subprocess.STDOUT)
    (out / (name + '.exitcode')).write_text(str(result.returncode) + '\n')
    results.append({'name': name, 'command': command, 'exitcode': result.returncode})
    print(name, result.returncode, flush=True)
(out / 'regression-results.json').write_text(json.dumps(results, indent=2) + '\n')
