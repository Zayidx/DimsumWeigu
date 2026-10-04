"""Local Edge CDP inspection using Python stdlib; no Node/browser package required.

Start an isolated headless Edge on port 9227. Pass login details over stdin:
{"email":"...","password":"...","pages":["/dashboard",...]}.
Credentials are never written to artifacts or printed.
"""
import base64
import json
import os
import socket
import struct
import sys
import time
import urllib.request
from pathlib import Path
from urllib.parse import urlparse


class Browser:
    def __init__(self, base_url='http://localhost:8000'):
        self.base_url = base_url
        with urllib.request.urlopen('http://localhost:9227/json/list') as response:
            pages = json.load(response)
        target = next(page for page in pages if page['type'] == 'page')
        url = urlparse(target['webSocketDebuggerUrl'])
        self.socket = socket.create_connection((url.hostname, url.port), timeout=20)
        self.socket.sendall((f'GET {url.path} HTTP/1.1\r\nHost: {url.netloc}\r\n'
                             'Upgrade: websocket\r\nConnection: Upgrade\r\n'
                             f'Sec-WebSocket-Key: {base64.b64encode(os.urandom(16)).decode()}\r\n'
                             'Sec-WebSocket-Version: 13\r\nOrigin: http://localhost:9227\r\n\r\n').encode())
        header = b''
        while not header.endswith(b'\r\n\r\n'):
            header += self.socket.recv(1)
        if b' 101 ' not in header:
            raise RuntimeError('Browser connection rejected')
        self.next_id = 0
        self.errors = []
        self.call('Page.enable')
        self.call('Runtime.enable')

    def read(self, length):
        result = b''
        while len(result) < length:
            chunk = self.socket.recv(length - len(result))
            if not chunk:
                raise RuntimeError('Browser disconnected')
            result += chunk
        return result

    def send(self, payload, opcode=1):
        mask = os.urandom(4)
        length = len(payload)
        size = bytes([length | 128]) if length < 126 else bytes([126 | 128]) + struct.pack('!H', length) if length < 65536 else bytes([127 | 128]) + struct.pack('!Q', length)
        self.socket.sendall(bytes([128 | opcode]) + size + mask + bytes(value ^ mask[index % 4] for index, value in enumerate(payload)))

    def receive(self):
        data = b''
        while True:
            first, second = self.read(2)
            length = second & 127
            if length == 126:
                length = struct.unpack('!H', self.read(2))[0]
            elif length == 127:
                length = struct.unpack('!Q', self.read(8))[0]
            mask = self.read(4) if second & 128 else None
            chunk = self.read(length)
            if mask:
                chunk = bytes(value ^ mask[index % 4] for index, value in enumerate(chunk))
            if first & 15 == 9:
                self.send(chunk, 10)
                continue
            if first & 15 == 8:
                raise RuntimeError('Browser websocket closed')
            data += chunk
            if first & 128:
                return json.loads(data)

    def call(self, method, params=None):
        self.next_id += 1
        self.send(json.dumps({'id': self.next_id, 'method': method, 'params': params or {}}).encode())
        while True:
            message = self.receive()
            if message.get('method') == 'Runtime.exceptionThrown':
                detail = message['params']['exceptionDetails']
                self.errors.append(detail.get('exception', {}).get('description', detail.get('text', 'JS error'))[:400])
            if message.get('id') == self.next_id:
                if 'error' in message:
                    raise RuntimeError(message['error']['message'])
                return message.get('result', {})

    def evaluate(self, expression):
        result = self.call('Runtime.evaluate', {'expression': expression, 'returnByValue': True, 'awaitPromise': True})
        if result.get('exceptionDetails'):
            raise RuntimeError('Browser evaluation failed')
        return result.get('result', {}).get('value')

    def navigate(self, path):
        self.errors = []
        self.call('Page.navigate', {'url': self.base_url + path})
        for _ in range(60):
            time.sleep(.1)
            if self.evaluate('document.readyState') == 'complete':
                break
        time.sleep(.2)

    def inspect(self):
        return self.evaluate('''JSON.stringify({
            path: location.pathname, title: document.title,
            width: innerWidth, scrollWidth: document.documentElement.scrollWidth,
            overflow: document.documentElement.scrollWidth > innerWidth,
            tableCards: document.querySelectorAll('table.mobile-records').length,
            mobileNav: !!document.querySelector('.workspace-mobile-navigation'),
            sidebarOpen: !!document.querySelector('#sidebar.active'),
            unlabeledInputs: [...document.querySelectorAll('input:not([type=hidden]):not([aria-hidden=true]), select, textarea')].filter(e => !e.getAttribute('aria-label') && !e.labels?.length).map(e=>e.id || e.name).slice(0,20),
            smallButtons: [...document.querySelectorAll('button, .btn, .page-link')].filter(e=>e.getClientRects().length && (e.getBoundingClientRect().height < 43 || e.getBoundingClientRect().width < 40)).map(e=>e.textContent.trim() || e.getAttribute('aria-label')).slice(0,20)
        })''')


def main():
    options = json.load(sys.stdin)
    browser = Browser(options.get('base_url', 'http://localhost:8000'))
    folder = Path('storage/app/ui-audit')
    folder.mkdir(parents=True, exist_ok=True)
    if options.get('logout_first'):
        browser.navigate('/dashboard')
        browser.evaluate('document.querySelector("form[action$=\\\"/logout\\\"]")?.requestSubmit()')
        time.sleep(.6)
    if options.get('email'):
        browser.navigate('/login')
        if browser.evaluate('location.pathname') != '/login':
            browser.evaluate('document.querySelector("form[action$=\\\"/logout\\\"]")?.requestSubmit()')
            time.sleep(.5)
            browser.navigate('/login')
        time.sleep(2.1)  # Login form timing guard.
        browser.evaluate('document.getElementById("email").value = ' + json.dumps(options['email']) + ';document.getElementById("password").value = ' + json.dumps(options['password']) + ';document.querySelector("form").requestSubmit();')
        time.sleep(1)
        if browser.evaluate('location.pathname') == '/login':
            raise RuntimeError('Login did not complete')
    if options.get('confirm_password'):
        browser.navigate('/confirm-password')
        browser.evaluate('document.querySelector("input[name=password]").value = ' + json.dumps(options['password']) + ';document.querySelector("form").requestSubmit();')
        time.sleep(.6)
    results = []
    for width in options.get('widths', [320, 390, 768, 1280]):
        browser.call('Emulation.setDeviceMetricsOverride', {'width': width, 'height': 844, 'deviceScaleFactor': 1, 'mobile': width < 768})
        for path in options.get('pages', ['/login']):
            browser.navigate(path)
            result = json.loads(browser.inspect())
            result['requested_width'] = width
            result['overflow'] = result['overflow'] or result['scrollWidth'] > width
            if result['overflow']:
                result['overflow_elements'] = browser.evaluate('JSON.stringify([...document.querySelectorAll("body *")].filter(e=>e.getClientRects().length && e.scrollWidth > e.clientWidth + 2 && e.clientWidth > 0).slice(0,25).map(e=>({tag:e.tagName,css:e.className,client:e.clientWidth,scroll:e.scrollWidth})))')
            result['requested'] = path
            result['javascript_errors'] = list(browser.errors)
            if options.get('screenshots', True):
                name = path.strip('/').replace('/', '-') or 'home'
                screenshot = browser.call('Page.captureScreenshot', {'format': 'png'})
                (folder / f'{name}-{width}.png').write_bytes(base64.b64decode(screenshot['data']))
            results.append(result)
    if options.get('cart_review'):
        browser.call('Emulation.setDeviceMetricsOverride', {'width': 390, 'height': 844, 'deviceScaleFactor': 1, 'mobile': True})
        browser.navigate('/pos')
        browser.evaluate('document.querySelector(".pos-mobile-cart-button").click()')
        time.sleep(.3)
        results.append({'interaction': 'cart_open', 'open': browser.evaluate('!!document.querySelector(".pos-cart-panel.is-open")'),
                        'total': browser.evaluate('document.querySelector(".pos-total").textContent.trim()'),
                        'body_scroll_locked': browser.evaluate('document.body.classList.contains("pos-cart-open")')})
        screenshot = browser.call('Page.captureScreenshot', {'format': 'png'})
        (folder / 'pos-cart-390.png').write_bytes(base64.b64decode(screenshot['data']))
        browser.evaluate('document.dispatchEvent(new KeyboardEvent("keydown", {key:"Escape",bubbles:true}))')
        time.sleep(.2)
        results.append({'interaction': 'cart_escape', 'closed': browser.evaluate('!document.querySelector(".pos-cart-panel.is-open")'),
                        'body_scroll_unlocked': browser.evaluate('!document.body.classList.contains("pos-cart-open")')})
    for review in options.get('form_reviews', []):
        browser.call('Emulation.setDeviceMetricsOverride', {'width': 390, 'height': 844, 'deviceScaleFactor': 1, 'mobile': True})
        browser.navigate(review['path'])
        browser.evaluate('document.querySelector(' + json.dumps(review['selector']) + ')?.click()')
        time.sleep(.8)
        result = json.loads(browser.inspect())
        result['interaction'] = 'open_form'
        result['form_visible'] = browser.evaluate('!!document.querySelector(".modal.d-block, .workspace-form-actions") || [...document.querySelectorAll("form")].some(form => form.hasAttribute("wire:submit"))')
        result['requested'] = review['path']
        result['javascript_errors'] = list(browser.errors)
        results.append(result)
    if options.get('sidebar'):
        browser.call('Emulation.setDeviceMetricsOverride', {'width': 390, 'height': 844, 'deviceScaleFactor': 1, 'mobile': True})
        browser.navigate('/dashboard')
        browser.evaluate('document.querySelector(".burger-btn").click()')
        time.sleep(.3)
        results.append({'interaction': 'sidebar_open', 'open': browser.evaluate('document.getElementById("sidebar").classList.contains("active")')})
        browser.evaluate('document.dispatchEvent(new KeyboardEvent("keydown", {key:"Escape",bubbles:true}))')
        time.sleep(.1)
        results.append({'interaction': 'sidebar_escape', 'closed': browser.evaluate('!document.getElementById("sidebar").classList.contains("active")')})
    report = {'pages': results}
    name = Path(options.get('report_name', 'browser-inspection')).name
    (folder / f'{name}.json').write_text(json.dumps(report, indent=2, ensure_ascii=False), encoding='utf-8')
    print(json.dumps(report, ensure_ascii=False))


if __name__ == '__main__':
    main()
