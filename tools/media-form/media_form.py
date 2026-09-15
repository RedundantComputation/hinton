"""Media form: add an entry to the website's media list without editing JSON by hand.

Double-click "Start media form.bat". A form opens in the browser. On submit this script:
  1. downloads the live pages/media/media.json,
  2. checks the entry and adds it (uploading its thumbnail, if one was attached),
  3. checks the whole list is still valid, and
  4. uploads the list under a temporary name, then renames it over media.json,
     so the website never sees a half-uploaded file.
A copy of the list as it was before each change is saved in the "backups" folder next to this script.

Setup on her Windows PC (once):
  - Her own CS account needs to be in the hintonweb group, and the site's pages/media and
    pages/media/assets folders need group write (chmod g+w) so her uploads can replace media.json.
  - Install Python 3 from python.org (tick "Add python.exe to PATH").
  - Copy this folder to her PC and set SSH_HOST below to "<her CS username>@cs.toronto.edu".
  - In PowerShell: ssh-keygen -t ed25519   (press Enter at every prompt: no passphrase)
  - Add the key to her account (answer "yes" to trust the server, then enter her CS password):
      type $env:USERPROFILE\\.ssh\\id_ed25519.pub | ssh <SSH_HOST> "mkdir -p ~/.ssh && cat >> ~/.ssh/authorized_keys"
  - Check: ssh -o BatchMode=yes <SSH_HOST> echo ok   must print ok without asking for anything.
Self-check (no server needed): python test_media_form.py
"""
import base64
import datetime
import json
import os
import re
import shlex
import shutil
import subprocess
import tempfile
import threading
import webbrowser
from http.server import BaseHTTPRequestHandler, HTTPServer

# ---- Settings ----
SSH_HOST = ""   # "<CS username>@cs.toronto.edu" of whoever uses this copy. Leave empty to use a local folder instead (for testing).
SITE_DIR = "/cs/htuser/hinton/public_html"   # the website's folder on SSH_HOST; or a local path when SSH_HOST is empty
SITE_URL = "https://www.cs.toronto.edu/~hinton/"
PORT = 8790

HERE = os.path.dirname(os.path.abspath(__file__))
MEDIA_JSON = 'pages/media/media.json'
ASSETS = 'pages/media/assets/'
# type -> (linkText used by existing entries, page that lists that type)
TYPES = {'video': ('Watch', 'video.html'), 'podcast': ('Listen', 'podcasts.html'),
         'print': ('Read', 'print.html'), 'book': ('Read', 'books.html')}
MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July',
          'August', 'September', 'October', 'November', 'December']
IMAGE_TYPES = {'image/png': '.png', 'image/jpeg': '.jpg'}
MAX_IMAGE_BYTES = 5 * 1024 * 1024


class FormError(Exception):
    """Something wrong with what was entered. The message is shown in the form."""


# ---- Moving files to and from the website folder ----
def run(args):
    result = subprocess.run(args, capture_output=True, text=True)
    if result.returncode != 0:
        raise RuntimeError(result.stderr.strip() or f'{args[0]} failed')


def download(rel_path, local_path):
    if SSH_HOST:
        run(['scp', '-q', '-o', 'BatchMode=yes', f'{SSH_HOST}:{SITE_DIR}/{rel_path}', local_path])
    else:
        shutil.copyfile(os.path.join(SITE_DIR, rel_path), local_path)


def upload(local_path, rel_path):
    if SSH_HOST:
        run(['scp', '-q', '-o', 'BatchMode=yes', local_path, f'{SSH_HOST}:{SITE_DIR}/{rel_path}'])
    else:
        shutil.copyfile(local_path, os.path.join(SITE_DIR, rel_path))


def rename(rel_from, rel_to):
    if SSH_HOST:
        run(['ssh', '-o', 'BatchMode=yes', SSH_HOST,
             'mv', shlex.quote(f'{SITE_DIR}/{rel_from}'), shlex.quote(f'{SITE_DIR}/{rel_to}')])
    else:
        os.replace(os.path.join(SITE_DIR, rel_from), os.path.join(SITE_DIR, rel_to))


# ---- Checking and building the entry ----
def build_entry(form, items):
    kind = form.get('type')
    if kind not in TYPES:
        raise FormError('Choose a media type.')
    title = (form.get('title') or '').strip()
    if not title:
        raise FormError('Enter a title.')
    month, year = form.get('month'), str(form.get('year') or '').strip()
    if month not in MONTHS or not re.fullmatch(r'(19|20)\d\d', year):
        raise FormError('Choose a month and enter a four-digit year.')
    link = (form.get('link') or '').strip()
    if not re.fullmatch(r'https?://\S+', link):
        raise FormError('Enter the full link, starting with https://')
    if any(item.get('link') == link for item in items):
        raise FormError('That link is already in the media list.')
    return {'type': kind, 'date': f'{month} {year}', 'title': title, 'link': link, 'linkText': TYPES[kind][0]}


def decode_image(data_url):
    match = re.fullmatch(r'data:(image/[a-z]+);base64,(.+)', data_url, re.S)
    if not match or match.group(1) not in IMAGE_TYPES:
        raise FormError('The thumbnail must be a PNG or JPG image.')
    data = base64.b64decode(match.group(2))
    if len(data) > MAX_IMAGE_BYTES:
        raise FormError('The thumbnail is larger than 5 MB. Use a smaller screenshot.')
    return data, IMAGE_TYPES[match.group(1)]


def thumbnail_name(title, year, ext, items):
    """Follows the assets naming: outlet after the last "|" plus year, e.g. new_yorker_2023_thumbnail.png"""
    words = re.findall(r'[a-z0-9]+', title.rsplit('|', 1)[-1].lower())[:4] or ['media']
    base = '_'.join(words) + f'_{year}_thumbnail'
    taken = {os.path.basename(item.get('thumbnail', '')) for item in items}
    name, n = base + ext, 2
    while name in taken:
        name, n = f'{base}_{n}{ext}', n + 1
    return name


def check_list(items):
    for number, item in enumerate(items, 1):
        if (item.get('type') not in TYPES or not item.get('title') or not item.get('link')
                or not re.fullmatch(r'[A-Z][a-z]+ \d{4}', item.get('date', ''))):
            raise RuntimeError(f'entry {number} in media.json is not valid')


def add_entry(form):
    with tempfile.TemporaryDirectory() as tmp:
        current = os.path.join(tmp, 'media.json')
        download(MEDIA_JSON, current)
        with open(current, encoding='utf-8') as f:
            items = json.load(f)

        entry = build_entry(form, items)
        image = decode_image(form['thumbnail']) if form.get('thumbnail') else None

        backups = os.path.join(HERE, 'backups')
        os.makedirs(backups, exist_ok=True)
        shutil.copyfile(current, os.path.join(backups, datetime.datetime.now().strftime('media-%Y%m%d-%H%M%S.json')))

        if image:
            data, ext = image
            name = thumbnail_name(entry['title'], entry['date'][-4:], ext, items)
            local_image = os.path.join(tmp, name)
            with open(local_image, 'wb') as f:
                f.write(data)
            upload(local_image, ASSETS + name)
            entry['thumbnail'] = './assets/' + name

        items.append(entry)
        text = json.dumps(items, ensure_ascii=False, indent=2) + '\n'   # same layout as the existing file
        check_list(json.loads(text))
        new_list = os.path.join(tmp, 'media.new.json')
        with open(new_list, 'w', encoding='utf-8', newline='\n') as f:
            f.write(text)
        upload(new_list, MEDIA_JSON + '.uploading')
        rename(MEDIA_JSON + '.uploading', MEDIA_JSON)
    return entry


# ---- The local web page ----
class Handler(BaseHTTPRequestHandler):
    def reply(self, status, body, content_type):
        self.send_response(status)
        self.send_header('Content-Type', content_type)
        self.send_header('Content-Length', str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def do_GET(self):
        if self.path != '/':
            return self.send_error(404)
        with open(os.path.join(HERE, 'form.html'), 'rb') as f:
            self.reply(200, f.read(), 'text/html; charset=utf-8')

    def do_POST(self):
        if self.path != '/add':
            return self.send_error(404)
        try:
            length = int(self.headers.get('Content-Length', 0))
            if length > MAX_IMAGE_BYTES * 2:
                raise FormError('The thumbnail is larger than 5 MB. Use a smaller screenshot.')
            entry = add_entry(json.loads(self.rfile.read(length)))
            result = {'ok': True, 'message': f'Added "{entry["title"]}".',
                      'page': SITE_URL + 'pages/media/' + TYPES[entry['type']][1]}
        except FormError as error:
            result = {'ok': False, 'message': str(error)}
        except Exception as error:
            result = {'ok': False, 'message': f'Upload failed; the media list was not changed. Details: {error}'}
        self.reply(200, json.dumps(result).encode(), 'application/json')


if __name__ == '__main__':
    if not SITE_DIR or not (SSH_HOST or os.path.isdir(SITE_DIR)):
        raise SystemExit('Fill in SSH_HOST (your CS username) at the top of media_form.py first.')
    url = f'http://127.0.0.1:{PORT}/'
    server = HTTPServer(('127.0.0.1', PORT), Handler)
    print(f'Media form is running at {url}  (close this window to stop it)')
    threading.Timer(0.5, webbrowser.open, [url]).start()
    server.serve_forever()
