# encoding=utf8
from __future__ import absolute_import
from __future__ import division
from __future__ import unicode_literals

from flask import Flask
from flask import render_template
from flask import request

from time import time
import re
import gzip
import StringIO
import urllib2
from urllib2 import HTTPError
from bs4 import BeautifulSoup
import requests

app = Flask(__name__)

known_keys = {
    'vann': 'vann',
    'fett': 'fett',
    'mettede fettsyrer': 'mettet',
    'trans-umettede fettsyrer': 'trans',
    'cis-enumettede fettsyrer': 'enumettet',
    'cis-flerumettede fettsyrer': 'flerumettet',
    'kolesterol': 'kolesterol',
    'karbohydrat': 'karbohydrater',
        'stivelse': 'stivelse',
        'mono- og disakkarider': 'sukkerarter',
        'mono- og disakkarider': 'sukkerarter',
        #'sukker tilsatt': '?',
        #'kostfiber': 'kostfiber',
    'protein': 'protein',
    #'alkohol': 'alkohol',
    # VITAMINER
    'vitamin a': 'vitamina',
        #'retinol': 'retinol',
        'beta-karoten': 'betakaroten',
    'vitamin d': 'vitamind',
    'vitamin e': 'vitamine',
    'tiamin': 'vitaminb1',
    'riboflavin': 'vitaminb2',
    'niacin': 'vitaminb3',
    'vitamin b6': 'vitaminb6',
    'folat': 'vitaminb9',
    'vitamin b12': 'vitaminb12',
    'vitamin c': 'vitaminc',
    # MINERALER
    'kalsium': 'kalsium',
    'jern': 'jern',
    'jod': 'jod',
    'natrium': 'natrium',
    'kalium': 'kalium',
    'magnesium': 'magnesium',
    'sink': 'sink',
    'selen': 'selen',
    'kopper': 'kopper',
    'fosfor': 'fosfor'
}

class NutritionalValue(object):

    def __init__(self, url='', name='', strip_null_values=False):
        self.items = []
        self.kJ = '?'
        self.kcal = '?'
        self.name = name
        self.url = url
        self.strip_null_values = strip_null_values 

    def add_item(self, name, val, unit):
        s = known_keys.get(name,'')
        if s != '':
            if val != "0" or self.strip_null_values == False:
                self.items.append([s,val,unit])
    
    def __unicode__(self):
        out = [u'{{Næringsinnhold',
            ' | navn = %s' % self.name,
            ' | mengde = 100 g',
            ' | energi = %s kcal / %s kJ' % (self.kcal, self.kJ)]
        for name, val, unit in self.items:
            out.append(u' | %s = %s %s' % (name,val,unit))
        out.append(' | kilde = [%s Matvaretabellen]' % self.url)
        out.append('}}')
        return '\n'.join(out)

    def __str__(self):
        return unicode(self).encode('utf-8')


def check_url(url, strip_null_values):

    if not re.match(r'^http://(www\.)matvaretabellen\.no', url): 
        return 'Ohlalalaa, thats a weird url, no? Not one of matvaretabellen.no, eh?'

    req = requests.get(url, headers={
        'User-Agent': u'Næringsinnhold (+http://toolserver.org/~danmichaelo/matvaretabellen)'.encode('utf-8'),
        'Referer': 'http://toolserver.org/~danmichaelo/matvaretabellen',
        'Accept-Encoding': 'gzip'
    })

    headers = req.headers
    data = req.text
    #if headers.get('Content-Encoding') in ('gzip', 'x-gzip'):
    #    data = gzip.GzipFile(fileobj=StringIO.StringIO(data)).read()
    
    soup = BeautifulSoup(data)

    title = soup.find(id='content').find('h2').text
    tpl = NutritionalValue(url, title, strip_null_values)
    content = soup.find(id='content')
    if content == None:
        return "invalid page"
    tbody = content.find('tbody')
    if tbody == None:
        return "invalid page"
    for row in tbody.find_all('tr'):
        cols = row.find_all('td')
        navn = cols[0].text.lower()
        val = cols[1].text
        unit = cols[2].text
        ref = cols[3].text
        reftext = cols[4].text
        if navn == 'kilojoule':
            tpl.kJ = val
        elif navn == 'kilokalorier':
            tpl.kcal = val
        else:
            tpl.add_item(navn, val, unit)

    return str(tpl)

@app.route('/')
def show_index():

    url = request.args.get('url', '')
    stripnull = request.args.get('strip-null-values', 'false') == 'true'

    if url != '':
        return check_url(url, stripnull)
    else:
        return render_template('main.html')

if __name__ == '__main__':
    app.run()

