#!/usr/bin/env python3
"""Build acquisition/data/first-1000.html: a filterable table over the staging database.

Stays in the git-ignored data folder because it holds personal data. All values are inserted
into the page as text, never as markup, because they come from outside sources.
Run with: python -I acquisition/pipeline/build_table.py
"""
import json
import pathlib
import sqlite3

ROOT = pathlib.Path(__file__).resolve().parents[2]
DATA = ROOT / "acquisition" / "data"

FIELDS = ["record_id", "type", "qid", "name", "country", "city", "org_type", "org_category", "industry", "website", "website_status",
          "founded_year", "exchange", "current_title", "current_org", "roles", "awards", "sitelinks", "description", "bio",
          "site_description", "criterion", "criterion_met", "evidence_url", "wikipedia_lead", "photo_path", "photo_source",
          "photo_licence", "photo_credit", "logo_path", "logo_source", "logo_licence", "logo_credit", "flags", "content_hash"]

PAGE = r"""<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>First 1,000: staging data</title>
<style>
:root{--bg:#f6f6f3;--panel:#fff;--ink:#1d1f1e;--muted:#5d645f;--line:#dcdfd9;--accent:#0b6b4f;--warn:#8a4b00;--warnbg:#fdf1de;--chip:#eef1ec}
@media (prefers-color-scheme:dark){:root{--bg:#141615;--panel:#1d201e;--ink:#eceeea;--muted:#a3aaa4;--line:#333935;--accent:#58c9a1;--warn:#f2b866;--warnbg:#3a2c14;--chip:#2a2e2b}}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font:14px/1.45 system-ui,-apple-system,"Segoe UI",sans-serif}
header{padding:20px 20px 8px}h1{font-size:22px;margin:0 0 4px}.sub{color:var(--muted);margin:0}
.notice{margin:12px 20px 0;padding:10px 14px;background:var(--warnbg);color:var(--warn);border-radius:8px}
.stats{display:flex;flex-wrap:wrap;gap:8px;padding:12px 20px 0}.stat{background:var(--panel);border:1px solid var(--line);border-radius:8px;padding:8px 12px;min-width:104px}
.stat b{display:block;font-size:18px}.stat span{color:var(--muted);font-size:12px}
form{position:sticky;top:0;z-index:5;background:var(--bg);display:flex;flex-wrap:wrap;gap:8px;padding:12px 20px;border-bottom:1px solid var(--line);align-items:end}
label{display:flex;flex-direction:column;font-size:12px;color:var(--muted);gap:3px}input,select,button{font:inherit;color:var(--ink);background:var(--panel);border:1px solid var(--line);border-radius:6px;padding:6px 8px;min-height:34px}
select,input{max-width:100%;min-width:0}label{min-width:0;max-width:100%}input[type=search]{width:230px}button{cursor:pointer}button:hover{border-color:var(--accent)}label.check{flex-direction:row;align-items:center;gap:6px;min-height:34px;color:var(--ink);font-size:13px}
.bar{display:flex;justify-content:space-between;align-items:center;padding:10px 20px;gap:12px;flex-wrap:wrap}.pager{display:flex;gap:6px;align-items:center}
.wrap{overflow-x:auto;padding:0 20px 24px}table{border-collapse:collapse;width:100%;min-width:1000px;background:var(--panel);border:1px solid var(--line)}
th,td{padding:8px 10px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top}th{white-space:nowrap;font-size:12px;color:var(--muted);user-select:none}
th.sort{cursor:pointer}th.sort:hover{color:var(--accent)}th[aria-sort=ascending]::after{content:" ▲"}th[aria-sort=descending]::after{content:" ▼"}
tr.row{cursor:pointer}tr.row:hover{background:var(--chip)}tr.row:focus{outline:2px solid var(--accent);outline-offset:-2px}
.thumb{width:48px;height:48px;object-fit:contain;background:#fff;border:1px solid var(--line);border-radius:6px;display:block}.none{width:48px;height:48px;border:1px dashed var(--line);border-radius:6px;display:grid;place-items:center;color:var(--muted);font-size:10px}
.name{font-weight:600}.bio{max-width:420px;color:var(--muted)}.chip{display:inline-block;background:var(--chip);border-radius:999px;padding:1px 8px;font-size:12px;margin:0 3px 3px 0;white-space:nowrap}.chip.warn{background:var(--warnbg);color:var(--warn)}
a{color:var(--accent)}dialog{max-width:760px;width:calc(100% - 32px);border:1px solid var(--line);border-radius:10px;background:var(--panel);color:var(--ink);padding:0}dialog::backdrop{background:#0008}
.dh{display:flex;gap:14px;padding:18px;border-bottom:1px solid var(--line)}.dh img{width:96px;height:96px;object-fit:contain;background:#fff;border-radius:8px;border:1px solid var(--line)}.dh h2{margin:0 0 4px;font-size:20px}
.db{padding:6px 18px 18px}dl{display:grid;grid-template-columns:150px 1fr;gap:6px 14px;margin:10px 0}dt{color:var(--muted)}dd{margin:0;overflow-wrap:anywhere}.close{margin-left:auto;align-self:flex-start}
@media (max-width:640px){header,.stats,form,.bar,.wrap{padding-left:16px;padding-right:16px}.notice{margin-left:16px;margin-right:16px}input[type=search]{width:100%}form label:not(.check){flex:1 1 44%}dl{grid-template-columns:1fr}dt{font-weight:600}}
</style></head><body>
<header><h1>First 1,000: organisations and entrepreneurs</h1><p class="sub" id="meta"></p></header>
<p class="notice"><b>Unreviewed staging data. Do not publish or share.</b> Compiled by script from Wikidata, Wikimedia Commons and organisations' own sites. No person has checked it and no Legal review has happened. Bios are built by template from Wikidata's description and structured facts, not written by AI. Logos from organisation sites are not licensed.</p>
<div class="stats" id="stats"></div>
<form id="f" onsubmit="return false">
<label>Search<input type="search" id="q" placeholder="Name, organisation, bio"></label>
<label>Type<select id="type"></select></label><label>Country<select id="country"></select></label>
<label>Criterion<select id="criterion"></select></label><label>Category<select id="org_category"></select></label><label>Organisation type<select id="org_type"></select></label>
<label>Website<select id="website_status"></select></label>
<label class="check"><input type="checkbox" id="hasPhoto">Has photo</label>
<label class="check"><input type="checkbox" id="hasLogo">Has logo</label>
<label class="check"><input type="checkbox" id="metOnly">Meets criterion</label>
<label class="check"><input type="checkbox" id="flagged">Has flags</label>
<button type="button" id="clear">Reset</button><button type="button" id="csv">Download CSV</button>
</form>
<div class="bar"><div id="count" role="status"></div><div class="pager"><button type="button" id="prev">Previous</button><span id="page"></span><button type="button" id="next">Next</button></div></div>
<div class="wrap"><table><thead><tr id="head"></tr></thead><tbody id="body"></tbody></table></div>
<dialog id="d"></dialog>
<script id="data" type="application/json">__DATA__</script>
<script>
const R=JSON.parse(document.getElementById('data').textContent),META=__META__;
const $=i=>document.getElementById(i),PAGE=50;let page=0,sortKey='sitelinks',sortDir=-1,view=[];
const el=(t,a={},...k)=>{const e=document.createElement(t);for(const[n,v]of Object.entries(a))e.setAttribute(n,v);for(const c of k)if(c!=null)e.append(c);return e};
const safe=u=>{try{const p=new URL(u);return p.protocol==='http:'||p.protocol==='https:'?u:null}catch{return null}};
const link=(u,t)=>safe(u)?el('a',{href:u,target:'_blank',rel:'noopener noreferrer'},t||u):(t||u||'');
const list=v=>{try{return JSON.parse(v||'[]')}catch{return[]}};
const CLABEL={O1:'O1 listed',O5:'O5 Wikipedia sitelink',E1:'E1 founder of listed company',LEAD:'Lead: not yet E1 or E2'};
R.forEach(r=>{r._flags=list(r.flags);r._roles=list(r.roles);r._awards=list(r.awards);r._text=[r.name,r.current_org,r.current_title,r.bio,r.description,r.site_description,r.industry,r.city].join(' ').toLowerCase();r._t=r.type==='organisation'?'Organisation':'Entrepreneur'});
function opts(id,key,fmt){const c={};R.forEach(r=>{if(r[key])c[r[key]]=(c[r[key]]||0)+1});const s=$(id);s.append(el('option',{value:''},'All'));Object.entries(c).sort((a,b)=>b[1]-a[1]).forEach(([v,n])=>s.append(el('option',{value:v},(fmt?fmt(v):v)+' ('+n+')')))}
opts('type','_t');opts('country','country');opts('criterion','criterion',v=>CLABEL[v]||v);opts('org_category','org_category');opts('org_type','org_type');const WS={ok:'Reached',no_website:'No website listed',http_error:'Site returned an error',unreachable:'Site unreachable',bad_url:'Domain not found or invalid',robots_blocked:'Not fetched: robots.txt',not_checked:'Not checked'};opts('website_status','website_status',v=>WS[v]||v);
const n=f=>R.filter(f).length;
[[R.length,'records'],[n(r=>r.type==='organisation'),'organisations'],[n(r=>r.type!=='organisation'),'entrepreneurs'],[n(r=>r.criterion_met),'meet a plan criterion'],[n(r=>r.photo_path),'with photo'],[n(r=>r.logo_path),'with logo'],[n(r=>r.website_status==='ok'),'website reached']].forEach(([v,l])=>$('stats').append(el('div',{class:'stat'},el('b',{},v.toLocaleString()),el('span',{},l))));
$('meta').textContent='Run '+META.run_id+' · built '+META.built;
const COLS=[['photo','Image'],['name','Name',1],['_t','Type',1],['country','Country',1],['role','Role or organisation type',1],['criterion','Criterion',1],['founded_year','Founded',1],['sitelinks','Sitelinks',1],['bio','Bio'],['website','Website']];
COLS.forEach(([k,l,s])=>{const th=el('th',s?{class:'sort',tabindex:'0',scope:'col'}:{scope:'col'},l);if(s){th.dataset.k=k;const go=()=>{if(sortKey===k)sortDir=-sortDir;else{sortKey=k;sortDir=k==='sitelinks'?-1:1}page=0;render()};th.onclick=go;th.onkeydown=e=>{if(e.key==='Enter')go()}}$('head').append(th)});
const roleOf=r=>r.type==='organisation'?(r.org_type||''):[r.current_title,r.current_org].filter(Boolean).join(', ');
const thumb=r=>{const p=r.photo_path&&r.type!=='organisation'?r.photo_path:(r.logo_path||r.photo_path);return p?el('img',{class:'thumb',src:p,alt:'',loading:'lazy'}):el('div',{class:'none'},'none')};
function apply(){const q=$('q').value.trim().toLowerCase(),t=$('type').value,c=$('country').value,cr=$('criterion').value,o=$('org_type').value,g=$('org_category').value,w=$('website_status').value;
view=R.filter(r=>(!q||r._text.includes(q))&&(!t||r._t===t)&&(!c||r.country===c)&&(!cr||r.criterion===cr)&&(!o||r.org_type===o)&&(!g||r.org_category===g)&&(!w||r.website_status===w)&&(!$('hasPhoto').checked||r.photo_path)&&(!$('hasLogo').checked||r.logo_path)&&(!$('metOnly').checked||r.criterion_met)&&(!$('flagged').checked||r._flags.length));
const val=r=>sortKey==='role'?roleOf(r):r[sortKey];
view.sort((a,b)=>{const x=val(a),y=val(b);if(x==null||x==='')return 1;if(y==null||y==='')return -1;return(typeof x==='number'&&typeof y==='number'?x-y:String(x).localeCompare(String(y)))*sortDir})}
function render(){apply();const pages=Math.max(1,Math.ceil(view.length/PAGE));page=Math.min(page,pages-1);const b=$('body');b.textContent='';
view.slice(page*PAGE,(page+1)*PAGE).forEach(r=>{const tr=el('tr',{class:'row',tabindex:'0'});const open=()=>show(r);tr.onclick=e=>{if(!e.target.closest('a'))open()};tr.onkeydown=e=>{if(e.key==='Enter')open()};
const crit=el('span',{class:'chip'+(r.criterion_met?'':' warn')},r.criterion);
tr.append(el('td',{},thumb(r)),el('td',{class:'name'},r.name),el('td',{},r._t),el('td',{},r.country),el('td',{},roleOf(r)),el('td',{},crit),el('td',{},r.founded_year||''),el('td',{},String(r.sitelinks)),el('td',{class:'bio'},r.bio||''),el('td',{},r.website?link(r.website,(()=>{try{return new URL(r.website).hostname.replace(/^www\./,'')}catch{return r.website}})()):''));b.append(tr)});
$('count').textContent=view.length.toLocaleString()+' of '+R.length.toLocaleString()+' records';$('page').textContent='Page '+(page+1)+' of '+pages;$('prev').disabled=page===0;$('next').disabled=page>=pages-1;
document.querySelectorAll('th[data-k]').forEach(th=>{if(th.dataset.k===sortKey)th.setAttribute('aria-sort',sortDir>0?'ascending':'descending');else th.removeAttribute('aria-sort')})}
function row(dl,k,v){if(v==null||v===''||(Array.isArray(v)&&!v.length))return;dl.append(el('dt',{},k),el('dd',{},v))}
function show(r){const d=$('d');d.textContent='';const img=r.type!=='organisation'?(r.photo_path||r.logo_path):(r.logo_path||r.photo_path);
const close=el('button',{type:'button',class:'close'},'Close');close.onclick=()=>d.close();
d.append(el('div',{class:'dh'},img?el('img',{src:img,alt:''}):el('div',{class:'none'},'none'),el('div',{},el('h2',{},r.name),el('div',{},[r._t,r.country].join(' · '))),close));
const dl=el('dl');row(dl,'Bio',r.bio);row(dl,'Wikidata description',r.description);row(dl,'Site description (source text, not for reuse)',r.site_description);row(dl,'Role',roleOf(r));row(dl,'Industry',r.industry);row(dl,'City',r.city);row(dl,'Founded',r.founded_year);row(dl,'Listed on',r.exchange);row(dl,'Public roles',r._roles.join('; '));row(dl,'Awards',r._awards.join('; '));
row(dl,'Criterion',CLABEL[r.criterion]||r.criterion);row(dl,'Evidence',r.evidence_url?link(r.evidence_url):null);row(dl,'Website',r.website?link(r.website):null);row(dl,'Website check',WS[r.website_status]||r.website_status);row(dl,'Wikipedia (lead only)',r.wikipedia_lead?link(r.wikipedia_lead):null);
if(r.photo_path){row(dl,'Photo licence',r.photo_licence);row(dl,'Photo credit',r.photo_credit);row(dl,'Photo source',r.photo_source?link(r.photo_source):null)}
if(r.logo_path){row(dl,'Logo licence',r.logo_licence);row(dl,'Logo credit',r.logo_credit);row(dl,'Logo source',r.logo_source?link(r.logo_source):null)}
row(dl,'Flags',r._flags.map(f=>f.replace(/_/g,' ')).join('; '));row(dl,'Content hash',r.content_hash);d.append(el('div',{class:'db'},dl));d.showModal();close.focus()}
['q','type','country','criterion','org_category','org_type','website_status','hasPhoto','hasLogo','metOnly','flagged'].forEach(i=>$(i).addEventListener('input',()=>{page=0;render()}));
$('prev').onclick=()=>{page--;render()};$('next').onclick=()=>{page++;render()};$('clear').onclick=()=>{$('f').reset();page=0;render()};
$('csv').onclick=()=>{const K=['name','_t','country','city','org_category','org_type','industry','current_title','current_org','founded_year','exchange','website','website_status','criterion','bio','record_id'];
const esc=v=>{let s=String(v??'');if(/^[=+\-@\t\r]/.test(s))s="'"+s;return'"'+s.replace(/"/g,'""')+'"'};const csv=[K.join(',').replace('_t','type'),...view.map(r=>K.map(k=>esc(r[k])).join(','))].join('\r\n');
const a=el('a',{href:URL.createObjectURL(new Blob(['﻿'+csv],{type:'text/csv'})),download:'first-1000-filtered.csv'});a.click();URL.revokeObjectURL(a.href)};
$('d').addEventListener('click',e=>{if(e.target===$('d'))$('d').close()});render();
</script></body></html>
"""


def main():
    con = sqlite3.connect(DATA / "staging.sqlite")
    con.row_factory = sqlite3.Row
    rows = [{k: r[k] for k in FIELDS} for r in con.execute("SELECT * FROM records ORDER BY sitelinks DESC")]
    payload = json.dumps(rows, ensure_ascii=False, separators=(",", ":")).replace("<", "\\u003c")
    import time
    meta = json.dumps({"run_id": "first-1000-2026-11-09", "built": time.strftime("%Y-%m-%d %H:%M")})
    out = DATA / "first-1000.html"
    out.write_text(PAGE.replace("__META__", meta).replace("__DATA__", payload), encoding="utf-8")
    print(out, len(rows), "records", out.stat().st_size // 1024, "KB")


if __name__ == "__main__":
    main()
