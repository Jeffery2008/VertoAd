    </div> <!-- 结束 content -->
  </div> <!-- 结束 wrapper -->

  <script>
    // 标记当前页面在侧边栏中为活跃状态
    document.addEventListener('DOMContentLoaded', function() {
      const currentPath = window.location.pathname;
      const navLinks = document.querySelectorAll('.sidebar .nav-link');
      navLinks.forEach(link => {
        link.classList.remove('active');
        const href = link.getAttribute('href');
        if (currentPath === href || (href !== '/admin/dashboard' && currentPath.startsWith(href))) {
          link.classList.add('active');
        }
      });
    });
  </script>
</body>
</html> 