#!/usr/bin/env python
# -*- coding: utf-8; tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- vim:fenc=utf-8:et:sw=4:ts=4:sts=4

import re
import gzip
import StringIO
import urllib2
from bs4 import BeautifulSoup

from flup.server.fcgi import WSGIServer
from mako.template import Template
from mako.lookup import TemplateLookup
from cgi import parse_qs, escape

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

    req = urllib2.Request(url, headers={
        'User-Agent': u'Næringsinnhold (+http://toolserver.org/~danmichaelo/matvaretabellen)'.encode('utf-8'),
        'Referer': 'http://toolserver.org/~danmichaelo/matvaretabellen',
        'Accept-Encoding': 'gzip'
    })
    f = urllib2.urlopen(req)
    headers = f.info()
    data = f.read()
    if headers.get('Content-Encoding') in ('gzip', 'x-gzip'):
        data = gzip.GzipFile(fileobj=StringIO.StringIO(data)).read()
    
    soup = BeautifulSoup(data)

    title = soup.find(id='content').find('h2').text
    tpl = NutritionalValue(url, title, strip_null_values)
    for row in soup.find(id='content').find('tbody').find_all('tr'):
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

def app(environ, start_response):

    start_response('200 OK', [('Content-Type', 'text/html')])

    lookup = TemplateLookup(directories=['.'], input_encoding='utf-8', output_encoding='utf-8')

    parameters = parse_qs(environ.get('QUERY_STRING', ''))
    if 'url' in parameters:
        yield check_url(parameters['url'][0], parameters['strip-null-values'][0] == "true")
    else:
        tpl = Template(filename='main.html', input_encoding='utf-8', output_encoding='utf-8', lookup=lookup)
        yield tpl.render_unicode().encode('utf-8')

WSGIServer(app).run()
