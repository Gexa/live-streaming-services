var crypto = require('crypto'),
    http = require('http'),
    querystring = require('querystring'),
    url = require('url'),
    compare = require('tsscmp');

var auth = require('basic-auth');

function generateSecurePathHash(expires, client_ip, secret) {
    if (!expires || !client_ip || !secret) throw new Error('Must provide all token components');

    var input = expires + ' ' + secret;
    var binaryHash = crypto.createHash('md5').update(input).digest();
    var base64Value = new Buffer(binaryHash).toString('base64');
    return base64Value.replace(/=/g, '').replace(/\+/g, '-').replace(/\//g, '_');
}

function getStreamUrl(hostname, ip, secret, watchKey) {
    const expiresTimestamp = new Date(Date.now() + (1000 * 60 * 30)).getTime();
    const expires = String(Math.round(expiresTimestamp / 1000));

    const token = generateSecurePathHash(expires, ip, secret);
    // const hostname = os.hostname();
    hostname = hostname.split(':')[0];

    return `//${hostname}/__strm__/${token}/${expires}/${watchKey}.m3u8`;
}

const server = http.createServer(function (req, res) {
    var credentials = auth(req);

    // Check credentials
    // The "check" function will typically be against your user store
    if (!credentials || !check(credentials.name, credentials.pass)) {
        res.statusCode = 401;
        res.setHeader('WWW-Authenticate', 'Basic realm="'+req.url+'"');
        res.end('Access denied');
    } else {
        var ip = req.headers['x-forwarded-for'] || req.connection.remoteAddress;
        const parsed = url.parse(req.url);
        const query  = querystring.parse(parsed.query);
        res.writeHead(200, {'Content-Type': 'text/html'}); // http header
        res.writeHead(200, {'Access-Control-Allow-Origin': '*'}); // TODO: own domain
        res.write(getStreamUrl(req.headers.host, ip, '18PLUS_LIVESTREAMING_ENGINE_V1', query.streamKey)); //write a response
        res.end(); //end the response
    }
});

function check (name, pass) {
    var valid = true;
    valid = compare(name, '_UtopiaLiveStreamingEngine_') && valid;
    valid = compare(pass, 'Sm5f2m=f[]NJe~aY') && valid;
    return valid;
}

server.listen(8080, function() {
    console.log('Stream Auth server is now running');
});