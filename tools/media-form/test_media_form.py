"""Self-check for media_form.py, run against a temporary copy of the site's media folder.
Run: python test_media_form.py
"""
import base64
import json
import os
import shutil
import sys
import tempfile

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import media_form as mf

REPO = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
PNG = 'data:image/png;base64,' + base64.b64encode(b'\x89PNG\r\n\x1a\nnot a real image').decode()

with tempfile.TemporaryDirectory() as site:
    shutil.copytree(os.path.join(REPO, 'pages', 'media'), os.path.join(site, 'pages', 'media'))
    mf.SSH_HOST, mf.SITE_DIR, mf.HERE = '', site, site
    media = os.path.join(site, 'pages', 'media', 'media.json')
    assets = os.path.join(site, 'pages', 'media', 'assets')
    before = open(media, encoding='utf-8').read()
    mf.check_list(json.loads(before))  # the real list passes the whole-list check

    good = {'type': 'print', 'title': 'Test Article | The Globe and Mail', 'month': 'March',
            'year': '2026', 'link': 'https://example.com/test'}

    def rejected(changes, expected_words):
        try:
            mf.add_entry({**good, **changes})
        except mf.FormError as error:
            assert expected_words in str(error), error
            return
        raise AssertionError(f'accepted a bad form: {changes}')

    rejected({'type': 'blog'}, 'media type')
    rejected({'title': '   '}, 'title')
    rejected({'month': 'Marzo'}, 'four-digit year')
    rejected({'year': '26'}, 'four-digit year')
    rejected({'link': 'example.com/test'}, 'https://')
    rejected({'link': json.loads(before)[0]['link']}, 'already in the media list')
    rejected({'thumbnail': 'data:image/gif;base64,R0lGODlh'}, 'PNG or JPG')
    assert open(media, encoding='utf-8').read() == before, 'a rejected form changed media.json'
    assert not os.path.exists(os.path.join(site, 'backups')), 'a rejected form made a backup'

    entry = mf.add_entry({**good, 'thumbnail': PNG})
    assert entry == {'type': 'print', 'date': 'March 2026', 'title': 'Test Article | The Globe and Mail',
                     'link': 'https://example.com/test', 'linkText': 'Read',
                     'thumbnail': './assets/the_globe_and_mail_2026_thumbnail.png'}, entry
    after = open(media, encoding='utf-8').read()
    assert json.loads(after)[:-1] == json.loads(before) and json.loads(after)[-1] == entry
    assert after.startswith(before.rstrip()[:-1].rstrip()), 'existing entries were reformatted'
    assert open(os.path.join(assets, 'the_globe_and_mail_2026_thumbnail.png'), 'rb').read().startswith(b'\x89PNG')
    assert not os.path.exists(media + '.uploading')
    assert len(os.listdir(os.path.join(site, 'backups'))) == 1
    assert open(os.path.join(site, 'backups', os.listdir(os.path.join(site, 'backups'))[0]), encoding='utf-8').read() == before

    second = mf.add_entry({**good, 'link': 'https://example.com/test-2', 'thumbnail': PNG})
    assert second['thumbnail'] == './assets/the_globe_and_mail_2026_thumbnail_2.png', second

    no_image = mf.add_entry({**good, 'type': 'video', 'link': 'https://www.youtube.com/watch?v=abcdefghijk', 'thumbnail': None})
    assert 'thumbnail' not in no_image and no_image['linkText'] == 'Watch'
    mf.check_list(json.load(open(media, encoding='utf-8')))

print('all checks passed')
