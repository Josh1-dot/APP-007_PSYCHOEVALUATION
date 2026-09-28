#!/usr/bin/env python3
"""Create a fresh .env with independent secrets. Never overwrites an existing file."""
from pathlib import Path
import base64
import secrets
import sys
root = Path(__file__).resolve().parent.parent
path = root / (sys.argv[1] if len(sys.argv) > 1 else '.env')
if path.exists():
    sys.exit(f'{path.name} existe déjà. Conservez-le ou sauvegardez-le avant de créer une autre configuration.')
text = (root / '.env.example').read_text()
replacements = {
    'APP_KEY=': 'APP_KEY=base64:' + base64.b64encode(secrets.token_bytes(32)).decode(),
    'DB_PASSWORD=': 'DB_PASSWORD=' + secrets.token_urlsafe(32),
    'MYSQL_ROOT_PASSWORD=': 'MYSQL_ROOT_PASSWORD=' + secrets.token_urlsafe(32),
    'DEMO_PASSWORD=': 'DEMO_PASSWORD=' + secrets.token_urlsafe(18),
}
lines = [replacements.get(line, line) for line in text.splitlines()]
with path.open('x') as config:
    config.write('\n'.join(lines) + '\n')
path.chmod(0o600)
print(f'Configuration MySQL créée dans {path.name}. Les secrets ne sont pas affichés.')
