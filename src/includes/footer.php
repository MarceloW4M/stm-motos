    </main>
    
    <footer class="footer">
        <p>&copy; <?php echo date('Y'); ?> STM - Aventura Motos. Todos los derechos reservados.</p>
    </footer>
    
    <script>
    // Funciones JavaScript comunes
    function confirmAction(message) {
        return confirm(message || '¿Está seguro de realizar esta acción?');
    }
    
    function showModal(modalId) {
        document.getElementById(modalId).style.display = 'block';
    }
    
    function hideModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    // Submenu para navegacion en dispositivos tactiles.
    document.querySelectorAll('.submenu-toggle').forEach(function(toggleBtn) {
        toggleBtn.addEventListener('click', function(event) {
            event.preventDefault();
            event.stopPropagation();

            var parent = toggleBtn.closest('.nav-dropdown');
            if (!parent) {
                return;
            }

            var isOpen = parent.classList.contains('open');
            document.querySelectorAll('.nav-dropdown.open').forEach(function(item) {
                item.classList.remove('open');
                var btn = item.querySelector('.submenu-toggle');
                if (btn) {
                    btn.setAttribute('aria-expanded', 'false');
                }
            });

            if (!isOpen) {
                parent.classList.add('open');
                toggleBtn.setAttribute('aria-expanded', 'true');
            }
        });
    });

    document.addEventListener('click', function(event) {
        document.querySelectorAll('.nav-dropdown.open').forEach(function(item) {
            if (!item.contains(event.target)) {
                item.classList.remove('open');
                var btn = item.querySelector('.submenu-toggle');
                if (btn) {
                    btn.setAttribute('aria-expanded', 'false');
                }
            }
        });
    });
    
    // Cerrar modal al hacer clic fuera de él
    window.onclick = function(event) {
        var modals = document.getElementsByClassName('modal');
        for (var i = 0; i < modals.length; i++) {
            if (event.target == modals[i]) {
                modals[i].style.display = 'none';
            }
        }
    }
    </script>
</body>
</html>
