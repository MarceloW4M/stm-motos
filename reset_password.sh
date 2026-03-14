#!/bin/bash
echo "🔄 Reseteando contraseña de admin..."

DB_HOST="10.50.0.33"
DB_PORT="3406"
DB_USER="root"
DB_NAME="stm_taller"

# Solicitar contraseña de MySQL root
read -s -p "🔑 Password de MySQL root: " MYSQL_PASS
echo

# Resetear contraseña de admin a 'admin123'
mysql -h $DB_HOST -P $DB_PORT -u $DB_USER -p$MYSQL_PASS $DB_NAME << EOF
UPDATE usuarios 
SET password = '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' 
WHERE username = 'admin';
EOF

echo "✅ Contraseña reseteada a: admin123"
