import os
import re
import json
import urllib.parse
import warnings
import urllib3

urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)
warnings.filterwarnings('ignore')

from flask import Flask, request, jsonify
from flask_cors import CORS
import requests
import cloudscraper

app = Flask(__name__)
CORS(app)

HEADERS = {
    'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
    'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
    'Accept-Language': 'en-US,en;q=0.9',
    'Accept-Encoding': 'gzip, deflate, br',
    'Connection': 'keep-alive',
    'Upgrade-Insecure-Requests': '1',
    'Referer': 'https://www.google.com/',
}

scraper = cloudscraper.create_scraper(
    browser={'browser': 'chrome', 'platform': 'windows', 'mobile': False}
)


def normalize_url(url: str) -> str:
    url = url.strip()
    if not url.startswith(('http://', 'https://')):
        url = 'https://' + url
    return url


def follow_redirects(url: str, max_hops: int = 20) -> dict:
    """Follow all redirects and return the final URL with chain info."""
    chain = []
    current = url
    session = requests.Session()
    session.headers.update(HEADERS)
    
    for i in range(max_hops):
        try:
            resp = session.get(current, allow_redirects=False, timeout=15, verify=False)
            chain.append({'step': i + 1, 'url': current, 'status': resp.status_code})
            
            if resp.status_code in (301, 302, 303, 307, 308):
                loc = resp.headers.get('Location', '')
                if not loc:
                    break
                if not loc.startswith('http'):
                    parsed = urllib.parse.urlparse(current)
                    loc = f"{parsed.scheme}://{parsed.netloc}{loc}"
                current = loc
            else:
                break
        except Exception as e:
            chain.append({'step': i + 1, 'url': current, 'error': str(e)})
            break
    
    return {'final_url': current, 'chain': chain, 'hops': len(chain)}


def bypass_bitly(url: str) -> dict:
    """Bypass bit.ly and similar redirect services."""
    result = follow_redirects(url)
    return result


def bypass_tinyurl(url: str) -> dict:
    result = follow_redirects(url)
    return result


def bypass_adfocus(url: str) -> dict:
    """Attempt to bypass adfly/adf.ly style links."""
    try:
        resp = scraper.get(url, timeout=15)
        content = resp.text
        
        patterns = [
            r"var\s+url\s*=\s*['\"]([^'\"]+)['\"]",
            r'window\.location\.href\s*=\s*[\'"]([^\'"]+)[\'"]',
            r'document\.location\s*=\s*[\'"]([^\'"]+)[\'"]',
            r'var\s+ysmData\s*=\s*[\'"]([^\'"]+)[\'"]',
            r'window\.location\s*=\s*[\'"]([^\'"]+)[\'"]',
        ]
        
        for pat in patterns:
            m = re.search(pat, content)
            if m:
                found = m.group(1)
                if found.startswith('http'):
                    return {'final_url': found, 'method': 'js_extract', 'chain': []}
        
        result = follow_redirects(url)
        return result
    except Exception as e:
        return {'error': str(e), 'final_url': url}


def bypass_linkvertise(url: str) -> dict:
    """Attempt to bypass Linkvertise links."""
    try:
        resp = scraper.get(url, timeout=20)
        content = resp.text
        
        patterns = [
            r'"url"\s*:\s*"([^"]+)"',
            r'window\.location\.href\s*=\s*[\'"]([^\'"]+)[\'"]',
            r'data-url=[\'"]([^\'"]+)[\'"]',
            r'href=[\'"]([^\'">]+)[\'"].*?(?:download|direct)',
        ]
        
        for pat in patterns:
            m = re.search(pat, content)
            if m:
                found = m.group(1)
                if found.startswith('http') and 'linkvertise' not in found:
                    return {'final_url': found, 'method': 'pattern_extract', 'chain': []}
        
        result = follow_redirects(url)
        return result
    except Exception as e:
        return {'error': str(e), 'final_url': url}


def bypass_ouoio(url: str) -> dict:
    """Bypass ouo.io links."""
    try:
        resp = scraper.get(url, timeout=15)
        content = resp.text
        
        m = re.search(r'<form[^>]+action="([^"]+)"', content)
        if m:
            action = m.group(1)
            token_m = re.search(r'name="_token"\s+value="([^"]+)"', content)
            token = token_m.group(1) if token_m else ''
            
            post_data = {'_token': token}
            resp2 = scraper.post(action, data=post_data, timeout=15, allow_redirects=False)
            
            if resp2.status_code in (301, 302, 303, 307):
                final = resp2.headers.get('Location', url)
                return {'final_url': final, 'method': 'form_bypass', 'chain': []}
        
        result = follow_redirects(url)
        return result
    except Exception as e:
        return {'error': str(e), 'final_url': url}


def bypass_shorte_st(url: str) -> dict:
    """Bypass shorte.st links."""
    try:
        resp = scraper.get(url, timeout=15)
        content = resp.text
        
        m = re.search(r"var\s+_targetLink\s*=\s*['\"]([^'\"]+)['\"]", content)
        if m:
            return {'final_url': m.group(1), 'method': 'js_var_extract', 'chain': []}
        
        m = re.search(r'href=[\'"]([^\'"]+)[\'"].*?class=[\'"].*?btn.*?[\'"]', content)
        if m:
            found = m.group(1)
            if found.startswith('http'):
                return {'final_url': found, 'method': 'btn_extract', 'chain': []}
        
        result = follow_redirects(url)
        return result
    except Exception as e:
        return {'error': str(e), 'final_url': url}


def bypass_generic_shortener(url: str) -> dict:
    """Generic bypass for unknown shorteners."""
    try:
        result = follow_redirects(url)
        if result['final_url'] == url and len(result['chain']) <= 1:
            try:
                resp = scraper.get(url, timeout=15)
                content = resp.text
                
                patterns = [
                    r'window\.location\.(?:href|replace)\s*[=\(]\s*[\'"]([^\'"]+)[\'"]',
                    r'document\.location\.(?:href|replace)\s*[=\(]\s*[\'"]([^\'"]+)[\'"]',
                    r'<meta[^>]+http-equiv=[\'"]refresh[\'"][^>]+content=[\'"][^;]+;\s*url=([^\'">\s]+)',
                    r'<a[^>]+href=[\'"]([^\'"]+)[\'"][^>]*>.*?(?:click|here|download|skip|continue)',
                    r'"redirect_url"\s*:\s*"([^"]+)"',
                    r'"url"\s*:\s*"(https?://[^"]+)"',
                    r'data-link=[\'"]([^\'"]+)[\'"]',
                ]
                
                for pat in patterns:
                    m = re.search(pat, content, re.IGNORECASE)
                    if m:
                        found = m.group(1).strip()
                        if found.startswith('http') and found != url:
                            return {'final_url': found, 'method': 'html_extract', 'chain': result['chain']}
            except Exception:
                pass
        
        return result
    except Exception as e:
        return {'error': str(e), 'final_url': url}


DOMAIN_HANDLERS = {
    'bit.ly': bypass_bitly,
    'bitly.com': bypass_bitly,
    'tinyurl.com': bypass_tinyurl,
    'rb.gy': follow_redirects,
    't.co': follow_redirects,
    'ow.ly': follow_redirects,
    'buff.ly': follow_redirects,
    'goo.gl': follow_redirects,
    'is.gd': follow_redirects,
    'v.gd': follow_redirects,
    'tiny.cc': follow_redirects,
    'cutt.ly': follow_redirects,
    'short.io': follow_redirects,
    'rebrand.ly': follow_redirects,
    'bl.ink': follow_redirects,
    'adf.ly': bypass_adfocus,
    'adfoc.us': bypass_adfocus,
    'j.gs': bypass_adfocus,
    'q.gs': bypass_adfocus,
    'linkvertise.com': bypass_linkvertise,
    'linkvertise.net': bypass_linkvertise,
    'ouo.io': bypass_ouoio,
    'ouo.press': bypass_ouoio,
    'shorte.st': bypass_shorte_st,
    'shrink.pe': bypass_generic_shortener,
    'bc.vc': bypass_generic_shortener,
    'clk.sh': bypass_generic_shortener,
    'exe.io': bypass_generic_shortener,
    'gplinks.co': bypass_generic_shortener,
    'gplinks.in': bypass_generic_shortener,
    'shrinkme.io': bypass_generic_shortener,
    'shrinkearn.com': bypass_generic_shortener,
    'fc.lc': bypass_generic_shortener,
    'cpmlink.net': bypass_generic_shortener,
    'ity.im': follow_redirects,
    'po.st': follow_redirects,
    'hubs.ly': follow_redirects,
    'youtu.be': follow_redirects,
    'amzn.to': follow_redirects,
    'amzn.eu': follow_redirects,
    'ebay.to': follow_redirects,
    'fb.me': follow_redirects,
    'ln.is': follow_redirects,
    'su.pr': follow_redirects,
}


def get_domain(url: str) -> str:
    try:
        parsed = urllib.parse.urlparse(url)
        domain = parsed.netloc.lower()
        if domain.startswith('www.'):
            domain = domain[4:]
        return domain
    except Exception:
        return ''


@app.route('/api/bypass', methods=['POST'])
def bypass_link():
    data = request.get_json(force=True)
    url = data.get('url', '').strip()
    
    if not url:
        return jsonify({'success': False, 'error': 'URL is required'}), 400
    
    url = normalize_url(url)
    
    try:
        urllib.parse.urlparse(url)
    except Exception:
        return jsonify({'success': False, 'error': 'Invalid URL format'}), 400
    
    domain = get_domain(url)
    
    handler = DOMAIN_HANDLERS.get(domain)
    
    if handler:
        if handler == follow_redirects:
            result = handler(url)
        else:
            result = handler(url)
    else:
        result = bypass_generic_shortener(url)
    
    if 'error' in result and not result.get('final_url'):
        return jsonify({
            'success': False,
            'error': result['error'],
            'original_url': url,
        })
    
    final_url = result.get('final_url', url)
    chain = result.get('chain', [])
    method = result.get('method', 'redirect_follow')
    
    bypassed = final_url != url
    
    return jsonify({
        'success': True,
        'original_url': url,
        'final_url': final_url,
        'bypassed': bypassed,
        'domain': domain,
        'hops': len(chain),
        'chain': chain,
        'method': method,
        'is_known_shortener': domain in DOMAIN_HANDLERS,
    })


@app.route('/api/health', methods=['GET'])
def health():
    return jsonify({'status': 'ok', 'version': '1.0.0'})


@app.route('/api/supported', methods=['GET'])
def supported_domains():
    return jsonify({
        'domains': list(DOMAIN_HANDLERS.keys()),
        'total': len(DOMAIN_HANDLERS),
        'note': 'Generic bypass also works for unlisted shorteners'
    })


if __name__ == '__main__':
    port = int(os.environ.get('PYTHON_PORT', 5001))
    app.run(host='0.0.0.0', port=port, debug=False)
