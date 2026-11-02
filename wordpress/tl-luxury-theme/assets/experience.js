(()=>{
 'use strict';
 const consentKey='tl-cookie-consent-v1',a11yKey='tl-native-a11y-v1';
 const banner=document.getElementById('tl-cookie-banner'),cookieDialog=document.getElementById('tl-cookie-dialog'),a11yDialog=document.getElementById('tl-a11y-dialog');
 const reduceQuery=window.matchMedia('(prefers-reduced-motion: reduce)');
 let preferences={version:1,necessary:true,analytics:false,marketing:false,updatedAt:''};
 const valid=value=>value&&value.version===1&&value.necessary===true&&typeof value.analytics==='boolean'&&typeof value.marketing==='boolean'&&typeof value.updatedAt==='string'&&Number.isFinite(Date.parse(value.updatedAt));
 const read=key=>{try{return JSON.parse(localStorage.getItem(key)||'null');}catch{return null;}};
 const stored=read(consentKey);if(valid(stored)){preferences=stored;if(banner)banner.hidden=true;}
 const sync=()=>{for(const name of ['analytics','marketing']){const field=document.querySelector('[name="tl-cookie-'+name+'"]');if(field)field.checked=preferences[name];}};
 sync();
 // Optional integrations must check this before loading and listen for consent revocation.
 window.tlCookieCategoryAllowed=category=>category==='necessary'||((category==='analytics'||category==='marketing')&&preferences[category]===true);
 function saveConsent(analytics,marketing){preferences={version:1,necessary:true,analytics:!!analytics,marketing:!!marketing,updatedAt:new Date().toISOString()};let saved=true;try{localStorage.setItem(consentKey,JSON.stringify(preferences));}catch{saved=false;}if(banner)banner.hidden=true;sync();window.dispatchEvent(new CustomEvent('tl-cookie-consent-change',{detail:{...preferences}}));const status=document.getElementById('tl-cookie-status');if(status)status.textContent=saved?'העדפות הפרטיות נשמרו. אפשר לשנות אותן בכל עת.':'ההעדפות תקפות לביקור הנוכחי. הדפדפן חסם שמירה במכשיר.';}
 function openDialog(dialog){if(!dialog)return;if(!dialog.open){dialog.tlOpener=document.activeElement;dialog.tlOverflow=document.documentElement.style.overflow;document.documentElement.style.overflow='hidden';dialog.showModal();dialog.querySelector('h2')?.focus();}}
 for(const dialog of [cookieDialog,a11yDialog]){dialog?.addEventListener('close',()=>{document.documentElement.style.overflow=dialog.tlOverflow||'';const opener=dialog.tlOpener;if(opener?.isConnected)opener.focus();});dialog?.querySelector('[data-tl-close]')?.addEventListener('click',()=>dialog.close());dialog?.addEventListener('keydown',event=>{if(event.key!=='Tab')return;const items=[...dialog.querySelectorAll('a[href],button,input,select,textarea,[tabindex]')].filter(item=>!item.disabled&&item.tabIndex>=0&&item.getClientRects().length);if(!items.length){event.preventDefault();return;}const current=items.indexOf(document.activeElement);if(current<0||(event.shiftKey&&current===0)||(!event.shiftKey&&current===items.length-1)){event.preventDefault();items[event.shiftKey?items.length-1:0].focus();}});}
 document.querySelectorAll('[data-tl-cookie-settings]').forEach(button=>button.addEventListener('click',()=>{sync();openDialog(cookieDialog);}));
 window.addEventListener('tl-open-cookie-settings',()=>{sync();openDialog(cookieDialog);});
 document.querySelector('[data-tl-cookie-reject]')?.addEventListener('click',()=>saveConsent(false,false));
 document.querySelector('[data-tl-cookie-accept]')?.addEventListener('click',()=>saveConsent(true,true));
 cookieDialog?.querySelector('form')?.addEventListener('submit',event=>{event.preventDefault();saveConsent(!!cookieDialog.querySelector('[name="tl-cookie-analytics"]')?.checked,!!cookieDialog.querySelector('[name="tl-cookie-marketing"]')?.checked);cookieDialog.close();});
 window.addEventListener('storage',event=>{if(event.key!==consentKey)return;const value=read(consentKey);preferences=valid(value)?value:{version:1,necessary:true,analytics:false,marketing:false,updatedAt:''};if(banner)banner.hidden=valid(value);sync();window.dispatchEvent(new CustomEvent('tl-cookie-consent-change',{detail:{...preferences}}));});
 const a11yNames={large:'tl-large',contrast:'tl-contrast',motion:'tl-reduce-motion',links:'tl-emphasize-links',focus:'tl-enhanced-focus'};
 let a11y=read(a11yKey);if(!a11y||typeof a11y!=='object'||Array.isArray(a11y))a11y={};
 function pauseFilms(){document.querySelectorAll('.tl-film video').forEach(video=>video.pause());}
 function applyA11y(){for(const [name,cls] of Object.entries(a11yNames)){const enabled=a11y[name]===true;document.body.classList.toggle(cls,enabled);const input=document.querySelector('[data-tl-pref="'+name+'"]');if(input)input.checked=enabled;}if(a11y.motion||reduceQuery.matches){pauseFilms();document.getAnimations?.().forEach(animation=>animation.cancel());}}
 applyA11y();
 document.querySelector('[data-tl-a11y-settings]')?.addEventListener('click',()=>openDialog(a11yDialog));
 a11yDialog?.querySelectorAll('[data-tl-pref]').forEach(field=>field.addEventListener('change',()=>{a11y[field.dataset.tlPref]=field.checked;applyA11y();try{localStorage.setItem(a11yKey,JSON.stringify(a11y));}catch{}}));
 document.querySelector('[data-tl-reset-a11y]')?.addEventListener('click',()=>{a11y={};applyA11y();try{localStorage.removeItem(a11yKey);}catch{}});
 reduceQuery.addEventListener('change',()=>{if(reduceQuery.matches){pauseFilms();document.getAnimations?.().forEach(animation=>animation.cancel());}});
 document.addEventListener('click',event=>{const target=event.target instanceof Element?event.target.closest('a[href],button,summary,input[type="checkbox"],input[type="radio"],select'):null;if(!target||a11y.motion||reduceQuery.matches||target.disabled||!target.animate)return;target.animate([{opacity:1},{opacity:.72},{opacity:1}],{duration:160,easing:'ease-out'});});
 const videos=[...document.querySelectorAll('.tl-film video')];
 videos.forEach(video=>video.addEventListener('error',()=>{const message=video.closest('.tl-film')?.querySelector('.tl-film-error');if(message)message.hidden=false;}));
 if('IntersectionObserver'in window){const observer=new IntersectionObserver(entries=>entries.forEach(entry=>{if(entry.intersectionRatio<.15)entry.target.pause();}),{threshold:[0,.15]});videos.forEach(video=>observer.observe(video));}
 document.addEventListener('visibilitychange',()=>{if(document.hidden)pauseFilms();});
})();
