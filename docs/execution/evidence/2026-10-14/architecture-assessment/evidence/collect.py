"""Read-only metadata collection; writes only this audit directory."""
import datetime, hashlib, json, pathlib, subprocess, sqlite3
R=pathlib.Path('/Users/hanifpriyambodo/Documents/online-shop')
D=pathlib.Path(__file__).resolve().parent
out={'time':datetime.datetime.now().astimezone().isoformat(),'commands':[]}
for cmd in [['php','-v'],['node','-v'],['npm','-v'],['composer','--version','--no-ansi'],['git','rev-parse','HEAD'],['git','branch','--show-current']]:
 p=subprocess.run(cmd,cwd=R,capture_output=True,text=True)
 out['commands'].append({'argv':cmd,'rc':p.returncode,'stdout_first_line':p.stdout.splitlines()[:1]})
lock=json.loads((R/'composer.lock').read_text()); ins=json.loads((R/'vendor/composer/installed.json').read_text())
out['php_packages']=[{'name':x['name'],'locked':x['version'],'installed':next((p['version'] for p in ins['packages'] if p['name']==x['name']),None)} for x in lock['packages']+lock['packages-dev']]
pkg=json.loads((R/'package.json').read_text()); nl=json.loads((R/'package-lock.json').read_text())
out['js_packages']=[]
for name,declared in pkg['devDependencies'].items():
 ip=R/'node_modules'/name/'package.json'
 out['js_packages'].append({'name':name,'declared':declared,'locked':nl['packages'].get('node_modules/'+name,{}).get('version'),'installed':json.loads(ip.read_text()).get('version') if ip.exists() else None})
# Allowlist env facts. Never serialize credentials or arbitrary environment values.
env={}
for line in (R/'.env.manual').read_text().splitlines():
 if '=' in line:
  k,v=line.split('=',1);env[k]=v.strip().strip('\"\'')
out['manual_config']={k:env.get(k) for k in ['APP_ENV','APP_DEBUG','APP_URL','DB_CONNECTION','SESSION_DRIVER','CACHE_STORE','QUEUE_CONNECTION','MAIL_MAILER']}
out['manual_config']['db_path_expected']=env.get('DB_DATABASE')==str(R/'storage/app/manual-test/database.sqlite')
out['manual_config']['db_url_empty']=env.get('DB_URL')==''
out['manual_config']['storage_overridden']='LARAVEL_STORAGE_PATH' in env
out['manual_config']['repo_cached_config_exists']=(R/'bootstrap/cache/config.php').exists()
out['manual_config']['isolation_verdict']='PARTIAL: manual DB isolated; storage, compiled views and logs shared with repository. No HTTP/bootstrap authorized by this audit gate.'
p=R/'storage/app/manual-test/database.sqlite'
if out['manual_config']['db_path_expected'] and p.is_file() and not p.is_symlink():
 db=sqlite3.connect('file:'+str(p)+'?mode=ro',uri=True)
 db.execute('pragma query_only=ON')
 out['manual_readonly_db']={'products':db.execute('select count(*) from products').fetchone()[0],'orders':db.execute('select status,count(*) from orders group by status').fetchall(),'migrations':db.execute('select count(*) from migrations').fetchone()[0],'schema':db.execute("select name,sql from sqlite_master where type='table' and name in ('products','orders','order_items','vouchers','addresses','reviews','favorites')").fetchall()}
 db.close()
(D/'stack-isolation.json').write_text(json.dumps(out,indent=2)+'\n')
files=['routes/web.php','app/Http/Controllers/CatalogController.php','app/Http/Controllers/StorefrontController.php','app/Http/Controllers/CartController.php','app/Http/Controllers/OrderController.php','app/Services/CheckoutService.php','app/Models/Order.php','app/Http/Controllers/AdminOrderController.php','app/Services/PdfDocumentService.php','app/Http/Middleware/SecurityHeaders.php']
text=['# Kutipan sumber kritis lengkap\n\nOBSERVED; nomor baris merujuk snapshot audit. Tidak menjalankan kode ini.\n']
for name in files:
 lines=(R/name).read_text().splitlines()
 text.append('\n## '+name+'\n\n```php\n'+'\n'.join(f'{i}: {s}' for i,s in enumerate(lines,1))+'\n```\n')
(D.parent/'SOURCE_EXCERPTS.md').write_text(''.join(text))
print(json.dumps({'rc':0,'packages':len(out['php_packages']),'manual_products':out.get('manual_readonly_db',{}).get('products'),'manual_orders':out.get('manual_readonly_db',{}).get('orders'),'isolation':'PARTIAL; no HTTP'},indent=2))
