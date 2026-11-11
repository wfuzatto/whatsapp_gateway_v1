#!/usr/bin/env python3
"""Inclui script v3 no layout atual da V1, com backup, sem trocar template."""
from pathlib import Path
import re, shutil, sys

if len(sys.argv) != 2:
    raise SystemExit('Uso: python instalar_layout_v3.py CAMINHO_DA_PASTA_V1')
root = Path(sys.argv[1]).resolve()
layout = root / 'app/views/layout.php'
inbox = root / 'app/views/pages/inbox.php'
if not layout.is_file() or not inbox.is_file():
    raise SystemExit('Arquivos PHP da V1 não encontrados. Nenhuma mudança feita.')
text = layout.read_text('utf-8')
if 'class="top-actions"' not in text or 'assets/js/app.js' not in text or 'href="?r=logout"' not in text:
    raise SystemExit('Layout diferente do esperado: revisar manualmente. Nenhuma mudança feita.')
if 'id="message-stream"' not in inbox.read_text('utf-8'):
    raise SystemExit('Inbox sem id=message-stream. Nenhuma mudança feita.')
include = '<script defer src="assets/js/inbox-scroll.js?v=20261111-3"></script>'
pattern = re.compile(r'<script\s+defer\s+src=["\']assets/js/inbox-scroll\.js(?:\?v=[^"\']*)?["\']\s*>\s*</script>', re.I)
if pattern.search(text):
    updated = pattern.sub(include, text, count=1)
else:
    anchor = '<script defer src="assets/js/app.js"></script>'
    if anchor not in text:
        raise SystemExit('Script app.js não encontrado. Nenhuma mudança feita.')
    updated = text.replace(anchor, anchor + include, 1)
if updated == text:
    print('Layout já atualizado.')
    raise SystemExit(0)
backup = Path(str(layout) + '.bak-ponte-v3')
if backup.exists():
    raise SystemExit(f'Backup {backup} já existe. Não sobrescrever.')
shutil.copy2(layout, backup)
layout.write_text(updated, encoding='utf-8')
print(f'Layout atualizado. Backup: {backup}. Enviar somente layout.php atualizado ao FTP.')
