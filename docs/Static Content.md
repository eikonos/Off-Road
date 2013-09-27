
# Static Content

Using apache and mod_rewrite is a good way to serve static content such as css, javascript and images.

I prefer to keep all site-specific files inside the site folder. This mod_rewrite configuration directs apache to serve files from the site/static folder while rewriting other urls to remove *index.php*.

    RewriteEngine on
    
    RewriteRule ^favicon\.ico$ site/static/favicon.ico [L]
    RewriteRule ^robots\.txt$ site/static/robots.txt [L]
    RewriteRule ^css/(.*)\.css$ site/static/css/$1.css [L]
    RewriteRule ^js/(.*)\.js$ site/static/js/$1.js [L]
    RewriteRule ^images/(.*)\.(jpg|jpeg|png|gif)$ site/static/images/$1.$2 [L]
    
    RewriteCond $1 !^index\.php
    RewriteCond $1 !^site/static/(.*)
    RewriteRule ^(.*)$ /index.php/$1 [L]
