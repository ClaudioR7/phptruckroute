</div><!-- .content -->
</div><!-- .main-wrapper -->

<script>
// Sidebar toggle for mobile
const sidebarToggle = document.getElementById('sidebar-toggle');
const sidebar = document.getElementById('sidebar');
if (sidebarToggle) {
  sidebarToggle.addEventListener('click', () => sidebar.classList.toggle('open'));
}
// Hide toggle if wide
function checkWidth() {
  if(window.innerWidth <= 768) {
    sidebarToggle && (sidebarToggle.style.display = 'flex');
  } else {
    sidebarToggle && (sidebarToggle.style.display = 'none');
    sidebar && sidebar.classList.remove('open');
  }
}
window.addEventListener('resize', checkWidth);
checkWidth();
</script>
</body>
</html>
