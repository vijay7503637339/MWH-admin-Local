</div></div>
<script>
const mm=document.getElementById('mobileMenu');
if(mm){mm.addEventListener('click',()=>document.body.classList.toggle('nav-open'));}
document.addEventListener('click',e=>{if(window.innerWidth>1000)return;if(e.target.closest('.sidebar')||e.target.closest('#mobileMenu'))return;document.body.classList.remove('nav-open');});
</script>
</body>
</html>
