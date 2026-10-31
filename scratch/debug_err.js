const http = require('http');

http.get('http://127.0.0.1:8000/news/1', (res) => {
    let data = '';
    res.on('data', chunk => data += chunk);
    res.on('end', () => {
        console.log('Status:', res.statusCode);
        const titleMatch = data.match(/<title>(.*?)<\/title>/i);
        console.log('Title:', titleMatch ? titleMatch[1] : 'none');
        
        let pos = data.indexOf('class="header');
        if (pos === -1) pos = data.indexOf('exception_title');
        if (pos === -1) pos = data.indexOf('text-xl');
        
        console.log('Error content:');
        console.log(data.slice(0, 2000).replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' '));
    });
}).on('error', e => console.log('Err:', e.message));
