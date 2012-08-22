#!/usr/bin/env python


"""This is a blind proxy that we use to get around browser
restrictions that prevent the WebGL from loading textures not on the
same server as the Javascript.  This has several problems: it's less
efficient, it might break some sites, and it's a security risk because
people can use this proxy to browse the web and possibly do bad stuff
with it.

Preferred solution for this cross-domain WebGL textures restriction
is usage of CORS HTTP headers on the server side.
"""

import urllib2
import cgi
import sys, os

import memcache
mc = memcache.Client(['127.0.0.1:11211'], debug=0)

# Designed to prevent Open Proxy type stuff.

allowedHosts = ['a.tile.openstreetmap.org','b.tile.openstreetmap.org','c.tile.openstreetmap.org',
                'otile1.mqcdn.com','otile2.mqcdn.com','otile3.mqcdn.com','otile4.mqcdn.com',
                'oatile1.mqcdn.com','oatile2.mqcdn.com','oatile3.mqcdn.com','oatile4.mqcdn.com',
                'webglearth.googlecode.com','demo.mapserver.org',
                'vmap0.tiles.osgeo.org',
		'api.europeana.eu' ]

def myapp(environ, start_response):

    fs = cgi.FieldStorage(environ['wsgi.input'], environ=environ)
    url = fs.getvalue('url', "//URL parameter not specified")

    try:
        host = url.split("/")[2]
        if allowedHosts and not host in allowedHosts:
            start_response('502 Bad Gateway', [('Content-Type', 'text/plain')])
            return ["This proxy does not allow you to access that location (%s)." % (host,) +
            "\n\n"+str(environ)]

        elif url.startswith("http://") or url.startswith("https://"):

            # load from memcached
	    s = mc.get(url)
            if s:
                if url.endswith('png'):
                    start_response('200 OK', [('Content-Type', 'image/png'),('Cache-Control', 'max-age=3600')])
                    return [s]
                else:
                    start_response('200 OK', [('Content-Type', 'image/jpeg'),('Cache-Control', 'max-age=3600')])
                    return [s]

            y = urllib2.urlopen(url)

            # content type header
            i = y.info()
            if i.has_key("Content-Type"):
                start_response('200 OK', [('Content-Type', i["Content-Type"]),('Cache-Control', 'max-age=3600')])
            else:
                start_response('200 OK', [('Content-Type', 'text/plain')])

            s = y.read()
            y.close()

            # save to memcached		
            if url.endswith('png') or url.endswith('jpg') or url.endswith('jpeg'):
                mc.set(url, s)

            return [s]
        else:
            start_response('404 Not Found', [('Content-Type', 'text/plain')])
            return ["Illegal request."]

    except Exception, E:
        start_response('500 Unexpected Error', [('Content-Type', 'text/plain')])
        return ["Some unexpected error occurred. Error text was: %s" % E]
        
if __name__ == '__main__':
    from flup.server.fcgi import WSGIServer
    WSGIServer(myapp).run()
