<div class="docs-try"
     x-data="{
         loading:false, statusCode:0, statusText:'', elapsed:0, responseRaw:'', tokenSaved:false,
         init(){
             const saved=localStorage.getItem('api_forge_token');
             if(saved&&!$wire.tryToken) $wire.set('tryToken',saved);
         },
         saveToken(){const t=$wire.tryToken;if(t){localStorage.setItem('api_forge_token',t);this.tokenSaved=true;setTimeout(()=>this.tokenSaved=false,2000);}},
         clearToken(){localStorage.removeItem('api_forge_token');$wire.set('tryToken','');},
         async send(){
             this.loading=true;this.statusCode=0;this.responseRaw='';
             const token=$wire.tryToken,method=$wire.selectedEndpoint?.method??'GET',params=$wire.tryQueryParams??[],body=$wire.tryBody;
             let url=$wire.tryUrl;
             const filled=params.filter(p=>p.value!=='');
             if(filled.length){const qs=new URLSearchParams();filled.forEach(p=>qs.append(p.key,p.value));url+='?'+qs.toString();}
             const headers={'Accept':'application/json','Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'};
             if(token?.trim()) headers['Authorization']='Bearer '+token.trim();
             const opts={method,headers};
             if(['POST','PUT','PATCH'].includes(method)) opts.body=body||'{}';
             const t0=performance.now();
             try{
                 const res=await fetch(url,opts);
                 this.elapsed=Math.round(performance.now()-t0);
                 this.statusCode=res.status;this.statusText=res.statusText;
                 const text=await res.text();
                 try{this.responseRaw=JSON.stringify(JSON.parse(text),null,2);}catch{this.responseRaw=text;}
             }catch(err){
                 this.elapsed=Math.round(performance.now()-t0);
                 this.statusCode=0;this.statusText='Network Error';
                 this.responseRaw=JSON.stringify({error:err.message},null,2);
             }
             this.loading=false;
         }
     }">

    @if($selectedEndpoint)
        <div class="try-head">
            <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.375rem;">
                <span class="mbc mbc-{{ strtolower($selectedEndpoint['method']) }}" style="font-size:.575rem;">{{ $selectedEndpoint['method'] }}</span>
                <span style="font-weight:700;font-size:.8125rem;color:var(--dv-text);">Try It</span>
            </div>
            <div style="font-family:ui-monospace,monospace;font-size:.7rem;color:var(--dv-faint);word-break:break-all;">{{ $selectedEndpoint['path'] }}</div>
            <div style="display:flex;align-items:center;gap:.375rem;margin-top:.5rem;flex-wrap:wrap;">
                <template x-if="statusCode > 0">
                    <span :class="scls(statusCode)" x-text="statusCode + ' ' + statusText"></span>
                </template>
                <template x-if="statusCode === 0 && !loading && responseRaw !== ''">
                    <span class="chip chip-0">Network Error</span>
                </template>
                <span x-show="elapsed > 0" x-cloak style="font-size:.68rem;color:var(--dv-faint);" x-text="elapsed + ' ms'"></span>
            </div>
        </div>

        <div class="try-body">
            <div>
                <div class="flabel">
                    Bearer Token
                    <button class="sm-btn" x-on:click="saveToken()">
                        <svg class="smbtn-icon" viewBox="0 0 20 20" fill="currentColor"><path d="M7 3a1 1 0 000 2h6a1 1 0 100-2H7zM4 7a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0 01-1-1zM2 11a2 2 0 012-2h12a2 2 0 012 2v4a2 2 0 01-2 2H4a2 2 0 01-2-2v-4z"/></svg>
                        <span x-text="tokenSaved ? '✓ Saved' : 'Save'"></span>
                    </button>
                    <button class="sm-btn" x-on:click="clearToken()">
                        <svg class="smbtn-icon" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                        Clear
                    </button>
                </div>
                <input type="password" class="finput" wire:model.blur="tryToken" placeholder="Paste your API token…">
                <div style="font-size:.63rem;color:var(--dv-faint);margin-top:.25rem;line-height:1.4;">Saved in browser localStorage — only sent to your own API.</div>
            </div>

            @if(!empty($tryQueryParams))
                <div>
                    <div class="flabel">Query Parameters</div>
                    @foreach($tryQueryParams as $i => $qp)
                        <div style="display:grid;grid-template-columns:100px 1fr;gap:.25rem;margin-bottom:.25rem;align-items:center;">
                            <span class="pname" style="font-size:.7rem;">{{ $qp['key'] }}</span>
                            <input type="text" class="finput" placeholder="{{ $qp['desc'] ?: $qp['type'] }}"
                                   wire:change="updateQueryParam({{ $i }}, $event.target.value)">
                        </div>
                    @endforeach
                </div>
            @endif

            @if(in_array($selectedEndpoint['method'], ['POST','PUT','PATCH']))
                <div>
                    <div class="flabel">Request Body <span style="font-weight:400;color:var(--dv-faint);">(JSON)</span></div>
                    <textarea class="ftextarea" wire:model.blur="tryBody">{{ $tryBody }}</textarea>
                </div>
            @endif

            <button class="send-btn" x-on:click="send()" x-bind:disabled="loading">
                <svg x-show="!loading" style="width:.8rem;height:.8rem;flex-shrink:0;" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd"/></svg>
                <svg x-show="loading" x-cloak style="width:.8rem;height:.8rem;flex-shrink:0;animation:spin 1s linear infinite;" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" opacity=".25"/><path d="M12 2a10 10 0 0110 10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                <span x-show="!loading">Send {{ $selectedEndpoint['method'] }}</span>
                <span x-show="loading" x-cloak>Sending…</span>
            </button>

            <div>
                <div class="flabel">Response</div>
                <pre class="codeblock" style="min-height:120px;max-height:320px;overflow:auto;white-space:pre-wrap;word-break:break-all;" x-html="hlJson(responseRaw)"></pre>
            </div>
        </div>

    @else
        <div class="try-empty">
            <svg style="width:2rem;height:2rem;opacity:.3;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.042 21.672L13.684 16.6m0 0l-2.51 2.225.569-9.47 5.227 7.917-3.286-.672zM12 2.25V4.5m5.834.166l-1.591 1.591M20.25 10.5H18M7.757 14.743l-1.59 1.59M6 10.5H3.75m4.007-4.243l-1.59-1.59"/></svg>
            <span>Select an endpoint to try it</span>
        </div>
    @endif
</div>
