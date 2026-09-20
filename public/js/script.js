document.addEventListener('DOMContentLoaded',()=>{
  const root=document.documentElement;
  const nav=document.querySelector('[data-nav]');
  const navToggle=document.querySelector('[data-nav-toggle]');
  if(nav&&navToggle){
    const close=()=>{nav.classList.remove('is-open');navToggle.setAttribute('aria-expanded','false');navToggle.querySelector('.sr-only').textContent='Buka menu navigasi';document.body.classList.remove('nav-open')};
    navToggle.addEventListener('click',()=>{const open=navToggle.getAttribute('aria-expanded')!=='true';nav.classList.toggle('is-open',open);navToggle.setAttribute('aria-expanded',String(open));navToggle.querySelector('.sr-only').textContent=open?'Tutup menu navigasi':'Buka menu navigasi';document.body.classList.toggle('nav-open',open)});
    nav.querySelectorAll('a').forEach(link=>link.addEventListener('click',close));document.addEventListener('keydown',event=>{if(event.key==='Escape')close()});window.addEventListener('resize',()=>{if(window.innerWidth>900)close()});
  }
  const themeButton=document.querySelector('[data-theme-toggle]');
  if(themeButton){themeButton.addEventListener('click',()=>{const next=root.getAttribute('data-theme')==='dark'?'light':'dark';root.setAttribute('data-theme',next);try{localStorage.setItem('mythesis-theme',next)}catch(e){}const meta=document.querySelector('meta[name="theme-color"]');if(meta)meta.setAttribute('content',next==='dark'?'#242C3E':'#FFFFFF')})}
  document.querySelectorAll('[data-password-toggle]').forEach(button=>{const input=document.getElementById(button.getAttribute('aria-controls'));if(!input)return;button.addEventListener('click',()=>{const reveal=input.type==='password';input.type=reveal?'text':'password';button.textContent=reveal?'Sembunyikan':'Lihat';button.setAttribute('aria-pressed',String(reveal));input.focus()})});
  document.querySelectorAll('input[type="file"]').forEach(input=>{const status=document.createElement('small');status.className='file-status';status.setAttribute('aria-live','polite');input.insertAdjacentElement('afterend',status);input.addEventListener('change',()=>{const file=input.files&&input.files[0];if(!file){status.textContent='';return}const size=file.size>=1048576?(file.size/1048576).toFixed(1)+' MB':Math.ceil(file.size/1024)+' KB';status.textContent=file.name+' · '+size})});
  document.querySelectorAll('form').forEach(form=>form.addEventListener('submit',event=>{if(event.defaultPrevented||!form.checkValidity())return;const button=form.querySelector('button[type="submit"],button:not([type])');if(button&&!form.dataset.noLock){button.disabled=true;button.dataset.originalText=button.textContent;button.textContent='Memproses…';button.setAttribute('aria-busy','true')}}));
});
