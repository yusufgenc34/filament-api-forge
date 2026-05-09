@php
function jt(mixed $d, int $lvl = 0): string {
    if (is_array($d)) {
        $assoc = !empty($d) && array_keys($d) !== range(0, count($d) - 1);
        if (empty($d)) return '<span class="jp">'.($assoc ? '{}' : '[]').'</span>';
        [$o,$c] = $assoc ? ['{','}'] : ['[',']'];
        $pad = str_repeat('  ', $lvl+1); $cp = str_repeat('  ', $lvl);
        $keys = array_keys($d); $last = count($keys)-1; $out = [];
        foreach ($keys as $i => $k) {
            $comma = $i < $last ? '<span class="jp">,</span>' : '';
            $key   = $assoc ? '<span class="jk">"'.e($k).'"</span><span class="jp">: </span>' : '';
            $out[] = "\n".$pad.$key.jt($d[$k], $lvl+1).$comma;
        }
        return '<span class="jp">'.$o.'</span>'.implode('', $out)."\n".$cp.'<span class="jp">'.$c.'</span>';
    }
    if (is_string($d))            return '<span class="js">"'.e($d).'"</span>';
    if (is_int($d)||is_float($d)) return '<span class="jn">'.$d.'</span>';
    if (is_bool($d))              return '<span class="jb">'.($d?'true':'false').'</span>';
    if (is_null($d))              return '<span class="jnl">null</span>';
    return e((string)$d);
}
function exVal(array $def): mixed {
    $t = $def['type'] ?? 'string';
    if ($t === 'integer') return 0;
    if ($t === 'boolean') return false;
    return '…';
}
@endphp

<script>
function hlJson(str){
    if(!str) return '<span class="jnl">// Response will appear here…</span>';
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
        .replace(/("(?:\\u[\da-fA-F]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(?:true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g,function(m){
            if(/^"/.test(m)) return/:$/.test(m)?'<span class="jk">'+m+'</span>':'<span class="js">'+m+'</span>';
            if(/true|false/.test(m)) return'<span class="jb">'+m+'</span>';
            if(/null/.test(m)) return'<span class="jnl">'+m+'</span>';
            return'<span class="jn">'+m+'</span>';
        });
}
function scls(c){if(!c||c===0)return'chip chip-0';if(c>=200&&c<300)return'chip chip-2xx';if(c>=400&&c<500)return'chip chip-4xx';return'chip chip-5xx';}
function buildSnippet(lang,method,url,token,body){
    method=(method||'GET').toUpperCase();
    const auth=(token||'').trim()||'YOUR_TOKEN';
    const hasBody=['POST','PUT','PATCH'].includes(method);
    let parsed=null;
    if(hasBody){try{parsed=JSON.parse(body||'{}')}catch(e){parsed={}}}
    const bodyStr=parsed?JSON.stringify(parsed,null,2):null;
    if(lang==='curl'){let c=`curl -X ${method} \\\n  '${url}' \\\n  -H 'Authorization: Bearer ${auth}' \\\n  -H 'Accept: application/json'`;if(bodyStr)c+=` \\\n  -H 'Content-Type: application/json' \\\n  -d '${bodyStr.replace(/\n/g,'\n  ')}'`;return c;}
    if(lang==='js'){let hdr=`    Authorization: 'Bearer ${auth}',\n    Accept: 'application/json',`;if(bodyStr)hdr+=`\n    'Content-Type': 'application/json',`;let o=`const response = await fetch('${url}', {\n  method: '${method}',\n  headers: {\n${hdr}\n  },`;if(bodyStr)o+=`\n  body: JSON.stringify(${bodyStr.replace(/\n/g,'\n  ')}),`;return o+`\n});\nconst data = await response.json();\nconsole.log(data);`;}
    if(lang==='php'){let p=`$response = Http::withToken('${auth}')\n    ->acceptJson()`;if(bodyStr){const arr=bodyStr.replace(/{/g,'[').replace(/}/g,']').replace(/:/g,' =>');p+=`\n    ->post('${url}', ${arr});`;}else{p+=`\n    ->${method.toLowerCase()}('${url}');`;}return p+`\n\n$data = $response->json();`;}
    if(lang==='python'){let py=`import requests\n\nheaders = {\n    'Authorization': 'Bearer ${auth}',\n    'Accept': 'application/json',\n}\n`;if(bodyStr){const d=bodyStr.replace(/true/g,'True').replace(/false/g,'False').replace(/null/g,'None');py+=`\npayload = ${d}\nresponse = requests.${method.toLowerCase()}(\n    '${url}',\n    json=payload,\n    headers=headers,\n)`;}else{py+=`\nresponse = requests.${method.toLowerCase()}(\n    '${url}',\n    headers=headers,\n)`;}return py+`\nprint(response.json())`;}
    return '';
}
</script>
