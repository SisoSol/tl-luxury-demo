document.querySelector('[data-tl-text]')?.addEventListener('click',function(){const on=document.body.classList.toggle('tl-large');this.setAttribute('aria-pressed',String(on));this.textContent=on?'גודל טקסט רגיל':'הגדלת טקסט';});
const menuToggle=document.querySelector('[data-tl-menu-toggle]'),catalogueMenu=document.getElementById('tl-catalogue-menu');
function closeCatalogue(){if(!catalogueMenu||!menuToggle)return;catalogueMenu.hidden=true;menuToggle.setAttribute('aria-expanded','false');}
menuToggle?.addEventListener('click',()=>{const open=menuToggle.getAttribute('aria-expanded')!=='true';menuToggle.setAttribute('aria-expanded',String(open));catalogueMenu.hidden=!open;});
document.addEventListener('keydown',event=>{if(event.key==='Escape'&&menuToggle?.getAttribute('aria-expanded')==='true'){closeCatalogue();menuToggle.focus();}});
document.addEventListener('pointerdown',event=>{if(!event.target.closest('.tl-header'))closeCatalogue();});
document.querySelector('.tl-header')?.addEventListener('focusout',event=>{if(event.relatedTarget&&!event.currentTarget.contains(event.relatedTarget))closeCatalogue();});
