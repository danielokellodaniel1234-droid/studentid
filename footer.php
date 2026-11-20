    <!-- Footer -->
    <footer class="footer" style="background-color: var(--dark-gray); color: white; padding: 20px 0; text-align: left; margin-top: 30px;">
        <div class="container">
            <p>&copy; 2023 Kumi University - Student ID Management System. All rights reserved.</p>
            <p style="margin-top: 10px; font-size: 12px; opacity: 0.8;">
                Logged in as: <?php echo htmlspecialchars($_SESSION['student_id'] ?? 'N/A'); ?> | 
                Session: <?php echo date('g:i a', $_SESSION['login_time'] ?? time()); ?>
            </p>
        </div>
    </footer>

    <script>
        // Auto-logout after 30 minutes of inactivity
        let timeout;
        function resetTimer() {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                window.location.href = 'logout.php?reason=timeout';
            }, 30 * 60 * 1000); // 30 minutes
        }

        // Reset timer on user activity
        document.addEventListener('mousemove', resetTimer);
        document.addEventListener('keypress', resetTimer);
        document.addEventListener('click', resetTimer);

        // Start the timer
        resetTimer();
    </script>
</body>
</html>