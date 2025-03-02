  </div> <!-- 结束 main-content -->

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
  <script>
    // 标记当前页面在侧边栏中为活跃状态
    document.addEventListener('DOMContentLoaded', function() {
      const currentPath = window.location.pathname;
      const navLinks = document.querySelectorAll('.sidebar .nav-link');
      navLinks.forEach(link => {
        link.classList.remove('active');
        if (currentPath.startsWith(link.getAttribute('href'))) {
          link.classList.add('active');
        }
      });
    });
  </script>
</body>
</html> 