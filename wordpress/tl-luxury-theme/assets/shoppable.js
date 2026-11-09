(()=>{'use strict';
 const hero=document.querySelector('.tl-video-hero'),video=hero?.querySelector('video'),toggle=hero?.querySelector('.tl-video-hero-toggle'),status=hero?.querySelector('.tl-video-hero-status');
 const reduced=matchMedia('(prefers-reduced-motion: reduce)'),mobile=matchMedia('(max-width:767px)'),connection=navigator.connection||navigator.mozConnection||navigator.webkitConnection;
 let onscreen=false,userPaused=false,failed=false,sourceGeneration=0,currentSource='';try{userPaused=sessionStorage.getItem('tl-hero-paused')==='1';}catch{}
 const reduce=()=>reduced.matches||document.body.classList.contains('tl-reduce-motion');
 const allowed=()=>!failed&&!reduce()&&!connection?.saveData&&!document.hidden&&onscreen&&!userPaused;
 function setState(){if(!video||!toggle)return;const playing=!video.paused;toggle.textContent=playing?'עצירת הסרטון':'הפעלת הסרטון';toggle.setAttribute('aria-label',playing?'עצירת סרטון האווירה':'הפעלת סרטון האווירה');toggle.setAttribute('aria-pressed',String(playing));}
 function maybePlay(){if(!video)return;if(allowed()){video.play().catch(setState);}else video.pause();}
 if(video&&toggle){
  const desktopPoster=video.getAttribute('poster'),mobilePoster=video.dataset.mobilePoster,desktopSource=video.dataset.desktopSrc,mobileSource=video.dataset.mobileSrc;
  function selectRendition(){
   const poster=mobile.matches&&mobilePoster?mobilePoster:desktopPoster;if(poster){video.setAttribute('poster',poster);hero.querySelectorAll('.tl-video-hero-backdrop,.tl-video-hero-still').forEach(img=>img.src=poster);}
   const next=mobile.matches&&mobileSource?mobileSource:desktopSource;if(!next||next===currentSource)return;
   const time=Number.isFinite(video.currentTime)?video.currentTime:0,generation=++sourceGeneration;currentSource=next;video.pause();failed=false;video.hidden=false;toggle.disabled=false;if(status)status.textContent='';
   video.addEventListener('loadedmetadata',()=>{if(generation!==sourceGeneration)return;if(time>0){try{video.currentTime=Number.isFinite(video.duration)?Math.min(time,Math.max(0,video.duration-.05)):time;}catch{}}maybePlay();},{once:true});
   video.setAttribute('src',next);video.load();maybePlay();
  }
  video.muted=true;video.defaultMuted=true;video.playsInline=true;
  video.addEventListener('play',setState);video.addEventListener('pause',setState);
  video.addEventListener('error',()=>{failed=true;if(status)status.textContent='הסרטון לא נטען. תמונת הפתיחה נשארת מוצגת.';video.hidden=true;toggle.disabled=true;toggle.textContent='תמונת הקמפיין';});
  toggle.addEventListener('click',()=>{if(video.paused){userPaused=false;try{sessionStorage.removeItem('tl-hero-paused');}catch{}video.play().catch(()=>{if(status)status.textContent='הסרטון לא התחיל. נסי שוב בעוד רגע.';setState();});}else{userPaused=true;try{sessionStorage.setItem('tl-hero-paused','1');}catch{}video.pause();}});
  if('IntersectionObserver'in window){const observer=new IntersectionObserver(entries=>{onscreen=entries[0].intersectionRatio>=.35;maybePlay();},{threshold:[0,.35]});observer.observe(video);}
  document.addEventListener('visibilitychange',maybePlay);reduced.addEventListener('change',maybePlay);connection?.addEventListener?.('change',maybePlay);new MutationObserver(maybePlay).observe(document.body,{attributes:true,attributeFilter:['class']});mobile.addEventListener('change',selectRendition);selectRendition();setState();
 }
 const activeDialogs=new Set();let savedOverflow='';
 document.querySelectorAll('.tl-product-hotspot').forEach(button=>{const dialog=document.getElementById(button.getAttribute('aria-controls'));if(!dialog)return;const close=()=>dialog.close();button.addEventListener('click',()=>{if(dialog.open)return;dialog.tlOpener=button;if(!activeDialogs.size){savedOverflow=document.documentElement.style.overflow;document.documentElement.style.overflow='hidden';}activeDialogs.add(dialog);dialog.showModal();dialog.querySelector('h3')?.focus();});dialog.querySelector('.tl-product-drawer-close')?.addEventListener('click',close);dialog.addEventListener('click',event=>{if(event.target===dialog){const rect=dialog.getBoundingClientRect();if(event.clientX<rect.left||event.clientX>rect.right||event.clientY<rect.top||event.clientY>rect.bottom)close();}});dialog.addEventListener('close',()=>{activeDialogs.delete(dialog);if(!activeDialogs.size)document.documentElement.style.overflow=savedOverflow;dialog.tlOpener?.focus();});dialog.addEventListener('keydown',event=>{if(event.key!=='Tab')return;const items=[...dialog.querySelectorAll('a[href],button,input,select,textarea,[tabindex]')].filter(item=>!item.disabled&&item.tabIndex>=0&&item.getClientRects().length);if(!items.length){event.preventDefault();return;}const index=items.indexOf(document.activeElement);if(index<0||(event.shiftKey&&index===0)||(!event.shiftKey&&index===items.length-1)){event.preventDefault();items[event.shiftKey?items.length-1:0].focus();}});});
})();
