# Structure du Routeur

Le routeur de l'Universal API est conçu pour la performance et la sécurité. Il permet de gérer des APIs REST complexes, comme celle de "THE LIFE COINCOIN".

## Définition des Routes
Les routes sont définies dans `hook/routes.php`.

```php
// Exemple
$router->get('/api/ducks', [DuckController::class, 'list'], ['middleware' => [AuthMiddleware::class]]);
```

Les routes dynamiques nécessitent des RegEx pour l'extraction des paramètres.
Les trailing slashes optionnels s'écrivent avec `/?`.
