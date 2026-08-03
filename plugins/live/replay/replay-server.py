#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
KOSA X CMS — moduł transmisji live: lokalny serwer powtórek z OBS (Etap 5).

Uruchamiany na KOMPUTERZE Z OBS (telebim = drugi ekran tego samego
komputera). Nasłuchuje folderu, do którego OBS zapisuje Replay Buffer,
i udostępnia NAJNOWSZY plik pod stałym adresem:

    http://localhost:8766/replay.mp4   → najnowszy klip (206/Range OK)
    http://localhost:8766/status.json  → {"file": ..., "mtime": ..., "size": ...}

Telebim (hostowany zdalnie, otwarty w kiosku na tym komputerze) odtwarza
klip przez <video src="http://localhost:8766/replay.mp4"> — localhost jest
zwolniony z blokady mixed-content w Chrome, a nagłówek CORS pozwala też
na fetch/diagnostykę.

Użycie (Python 3, TYLKO biblioteka standardowa — zero instalacji):

    python replay-server.py "C:\\Users\\obs\\Videos"            # port 8766
    python replay-server.py "C:\\Users\\obs\\Videos" --port 9000

W OBS: Ustawienia → Wyjście → Bufor powtórek (włącz) + format nagrywania
mp4/fMP4 (Ustawienia → Wyjście → Nagrywanie → Format). Skrót klawiszowy
„Zapisz powtórkę" zapisuje klip do folderu — serwer sam poda najnowszy.

Bezpieczeństwo: serwer wiąże się z 127.0.0.1 (tylko ten komputer),
serwuje wyłącznie pliki wideo z JEDNEGO wskazanego folderu (bez podkatalogów,
bez path traversal — nazwa pliku nie wychodzi poza folder).
"""

import argparse
import json
import os
import sys
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

VIDEO_EXTENSIONS = ('.mp4', '.m4v', '.mov', '.mkv', '.webm')

CONTENT_TYPES = {
    '.mp4':  'video/mp4',
    '.m4v':  'video/mp4',
    '.mov':  'video/quicktime',
    '.mkv':  'video/x-matroska',
    '.webm': 'video/webm',
}

WATCH_DIR = '.'


def newest_video(directory):
    """Najnowszy (mtime) plik wideo w folderze albo None."""
    newest = None
    newest_mtime = -1.0
    try:
        for name in os.listdir(directory):
            if not name.lower().endswith(VIDEO_EXTENSIONS):
                continue
            path = os.path.join(directory, name)
            if not os.path.isfile(path):
                continue
            mtime = os.path.getmtime(path)
            if mtime > newest_mtime:
                newest, newest_mtime = path, mtime
    except OSError:
        return None
    return newest


class ReplayHandler(BaseHTTPRequestHandler):
    protocol_version = 'HTTP/1.1'

    def log_message(self, fmt, *args):
        sys.stderr.write('[replay] %s\n' % (fmt % args))

    def _cors(self):
        self.send_header('Access-Control-Allow-Origin', '*')
        self.send_header('Cache-Control', 'no-store')

    def _json(self, code, payload):
        body = json.dumps(payload).encode('utf-8')
        self.send_response(code)
        self._cors()
        self.send_header('Content-Type', 'application/json; charset=utf-8')
        self.send_header('Content-Length', str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def do_OPTIONS(self):
        self.send_response(204)
        self._cors()
        self.send_header('Access-Control-Allow-Methods', 'GET, HEAD, OPTIONS')
        self.send_header('Access-Control-Allow-Headers', 'Range')
        self.send_header('Content-Length', '0')
        self.end_headers()

    def do_HEAD(self):
        self.do_GET(head_only=True)

    def do_GET(self, head_only=False):
        path = self.path.split('?', 1)[0]

        if path == '/status.json':
            clip = newest_video(WATCH_DIR)
            if clip is None:
                self._json(404, {'ok': False, 'error': 'Brak plikow wideo w folderze.'})
                return
            self._json(200, {
                'ok': True,
                'file': os.path.basename(clip),
                'mtime': int(os.path.getmtime(clip)),
                'size': os.path.getsize(clip),
            })
            return

        # kazda inna sciezka (w tym /replay.mp4) = najnowszy klip
        clip = newest_video(WATCH_DIR)
        if clip is None:
            self._json(404, {'ok': False, 'error': 'Brak plikow wideo w folderze.'})
            return

        size = os.path.getsize(clip)
        ctype = CONTENT_TYPES.get(os.path.splitext(clip)[1].lower(), 'application/octet-stream')

        # Range — <video> Chrome uzywa go do seekowania
        start, end = 0, size - 1
        range_header = self.headers.get('Range', '')
        is_partial = False
        if range_header.startswith('bytes='):
            try:
                spec = range_header[6:].split(',')[0].strip()
                left, _, right = spec.partition('-')
                if left:
                    start = int(left)
                    if right:
                        end = min(int(right), size - 1)
                elif right:  # bytes=-N (koncowka pliku)
                    start = max(0, size - int(right))
                is_partial = True
            except ValueError:
                start, end, is_partial = 0, size - 1, False
        if start > end or start >= size:
            self.send_response(416)
            self._cors()
            self.send_header('Content-Range', 'bytes */%d' % size)
            self.send_header('Content-Length', '0')
            self.end_headers()
            return

        length = end - start + 1
        self.send_response(206 if is_partial else 200)
        self._cors()
        self.send_header('Content-Type', ctype)
        self.send_header('Accept-Ranges', 'bytes')
        self.send_header('Content-Length', str(length))
        if is_partial:
            self.send_header('Content-Range', 'bytes %d-%d/%d' % (start, end, size))
        self.end_headers()

        if head_only:
            return

        try:
            with open(clip, 'rb') as handle:
                handle.seek(start)
                remaining = length
                while remaining > 0:
                    chunk = handle.read(min(65536, remaining))
                    if not chunk:
                        break
                    self.wfile.write(chunk)
                    remaining -= len(chunk)
        except (BrokenPipeError, ConnectionResetError):
            pass  # przegladarka zerwala pobieranie (seek/stop) — normalne


def main():
    global WATCH_DIR
    parser = argparse.ArgumentParser(description='Serwer powtorek OBS dla telebimu (KOSA X CMS).')
    parser.add_argument('folder', help='folder zapisu Replay Buffera OBS')
    parser.add_argument('--port', type=int, default=8766, help='port nasluchu (domyslnie 8766)')
    args = parser.parse_args()

    WATCH_DIR = os.path.abspath(args.folder)
    if not os.path.isdir(WATCH_DIR):
        sys.exit('Folder nie istnieje: %s' % WATCH_DIR)

    server = ThreadingHTTPServer(('127.0.0.1', args.port), ReplayHandler)
    print('Serwer powtorek: http://localhost:%d/replay.mp4' % args.port)
    print('Folder: %s' % WATCH_DIR)
    print('Ctrl+C konczy.')
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        pass


if __name__ == '__main__':
    main()
