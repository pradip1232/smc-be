# SMC — Admin & User API scaffold

Files created:

- admin/api/db.php — DB connection for admin APIs
- admin/api/login.php — simple admin login endpoint (JSON)
- user/api/db.php — DB connection for user APIs
- user/api/register.php — simple user registration endpoint (JSON)
- db/schema.sql — database schema + stored procedure
- db/db_con.php — single shared DB connection used by both admin and user APIs
- admin/api/db.php — wrapper that loads `db/db_con.php`
- user/api/db.php — wrapper that loads `db/db_con.php`
- admin/api/products.php — admin product CRUD + public listing endpoint (single file)
- admin/api/category.php — admin-only category CRUD endpoint (GET/POST/PUT/DELETE)

Setup:

1. Place this `smc` folder under your XAMPP `htdocs` (already done if you used the provided path).
2. Open phpMyAdmin (http://localhost/phpmyadmin) and import `db/schema.sql` to create the database and tables.
3. Edit DB credentials in `db/db_con.php` if you do not use default `root`/empty password.

Debugging database connection failures:

- To show detailed PDO exception messages (only enable on local/dev), set environment variable `DB_DEBUG=1` before starting Apache/PHP or add it to your environment.
- When `DB_DEBUG` is enabled, the connection error response will include the exception message.

Testing examples (PowerShell / curl):

```powershell
# Register user
curl -X POST http://localhost/smc/user/api/register.php -H "Content-Type: application/json" -d '{"name":"Alice","email":"alice@example.com","password":"secret"}'

# Admin login
curl -X POST http://localhost/smc/admin/api/login.php -H "Content-Type: application/json" -d '{"username":"admin","password":"demo123"}'
 
 # Create product
curl -X POST http://localhost/smc/admin/api/products.php -H "Content-Type: application/json" -H "X-ADMIN-TOKEN: change_me_admin_token" -d '{"product_id":"BAG-001","product_name":"Classic Backpack","price":39.99,"status":"published","stock":10}'

# Create product with image upload (multipart)
curl -X POST http://localhost/smc/admin/api/products.php -H "X-ADMIN-TOKEN: change_me_admin_token" -F "product_id=BAG-002" -F "product_name=Upload Bag" -F "price=49.99" -F "status=published" -F "images[]=@/path/to/image1.jpg" -F "images[]=@/path/to/image2.jpg"

 # Update product
 curl -X PUT http://localhost/smc/admin/api/products.php -H "Content-Type: application/json" -H "X-ADMIN-TOKEN: change_me_admin_token" -d '{"id":1,"price":34.99,"stock":15}'

 # Delete product
 curl http://localhost/smc/admin/api/products.php?id=1 -H "X-ADMIN-TOKEN: change_me_admin_token" -X DELETE

 # List published products for users (public)
 curl http://localhost/smc/admin/api/products.php

# Category (admin-only) examples
# List categories
curl -H "X-ADMIN-TOKEN: change_me_admin_token" http://localhost/smc/admin/api/category.php

# Get single category by id
curl -H "X-ADMIN-TOKEN: change_me_admin_token" http://localhost/smc/admin/api/category.php?id=1

# Create category
curl -X POST -H "Content-Type: application/json" -H "X-ADMIN-TOKEN: change_me_admin_token" -d '{"category_id":"CAT-01","name":"Kids","description":"Kids backpacks"}' http://localhost/smc/admin/api/category.php

# Update category
curl -X PUT -H "Content-Type: application/json" -H "X-ADMIN-TOKEN: change_me_admin_token" -d '{"id":1,"name":"Updated Kids"}' http://localhost/smc/admin/api/category.php

# Delete category
curl -X DELETE -H "X-ADMIN-TOKEN: change_me_admin_token" http://localhost/smc/admin/api/category.php?id=1
```

Notes:

- The sample endpoints are minimal and intended as starting points for API development.
- Use `password_hash` when inserting admin passwords. To create an initial admin, run a PHP snippet to generate the hash and insert it into the `admins` table.
