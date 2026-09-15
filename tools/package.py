"""Build a source-only Kimai plugin ZIP without local settings or runtime data."""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
import hashlib, json

root = Path(__file__).resolve().parents[1]
metadata = json.loads((root / 'composer.json').read_text(encoding='utf8'))
output = root / 'build'
output.mkdir(exist_ok=True)
target = output / ('KankaInvoiceMailBundle-' + metadata['version'] + '.zip')
files = [root / name for name in ['composer.json', 'KankaInvoiceMailBundle.php', 'README.md', 'LICENSE', 'CHANGELOG.md', 'SECURITY.md', 'TODO.md']]
for directory in ['Controller', 'DependencyInjection', 'EventSubscriber', 'Service', 'Resources', 'docs']:
    files.extend(path for path in (root / directory).rglob('*') if path.is_file() and path.suffix in ['.php', '.yaml', '.twig', '.md'])
with ZipFile(target, 'w', ZIP_DEFLATED) as archive:
    for path in sorted(files):
        archive.write(path, 'KankaInvoiceMailBundle/' + path.relative_to(root).as_posix())
digest = hashlib.sha256(target.read_bytes()).hexdigest()
target.with_suffix('.zip.sha256').write_text(digest + '  ' + target.name + '\n', encoding='ascii')
print(target.name + ': ' + digest)
