# TurboFilter

**Advanced Eloquent Custom Filter para Laravel**  

TurboFilter permite aplicar filtros dinámicos y realizar consultas flexibles sobre tus modelos Eloquent, incluyendo paginación automática, búsquedas y filtros por relaciones anidadas. Todo con una sintaxis simple y reutilizable.

---

## **1. Instalación**

* Instala el paquete vía Composer:

```bash
composer require cchery/turbo-filter 
```
* Publicar archivo de configuración

```bash
php artisan vendor:publish --provider="TurboFilter\TurboFilterServiceProvider" --tag=config
```
## **2. Conceptos Clave**
TurboFilter agrega scopes a tus modelos para simplificar consultas complejas.  

Los scopes principales son: ``filter()``, ``getOrPaginate()`` y ``customGet()``.


## **3. Implementación**

### Primero preparamos el modelo

* Para usar TurboFilter en un modelo, solo debes usar el trait ``HasTurboFilters`` como se muestra a continuación:

```php
  namespace App\Models;
  
  use Illuminate\Database\Eloquent\Model;
  use TurboFilter\Traits\HasTurboFilters;
  
  class User extends Model{

      use HasTurboFilters;
  
      const FILTER1 = ['name', 'email', 'departamento_id, 'profile.address.city:city_name,another_field1,another_field2'];
  }
```
* Notas importantes:

  1. Las constantes de filtros definen los únicos campos sobre los que se pueden aplicar búsquedas.

  2. Puedes tener varias constantes si quieres distintos conjuntos de filtros por modelo.

  3. Para relaciones, sigue la estructura: relacion1.relacion2#:campo1,campo2.
 
 ### Aplicando los scopes

## ``filter()``

* Antes de "filtrar" nesecitas saber que la estructura de datos de filtrado debe ser de la siguiente forma, de lo contrario no funcionará:

1. Filtro por búsqueda simple:
```js
{
    "search": "John"
}
//Con este input se filtrará por todas las referencias de "Jhon" con *like*
```

2. Filtro por by:
```js
{
  "by": {
      "email": "john@example.com",
      "city_name": "New York",
      "departamento_id": [1,2,3,4,5]
  }
}
/*
  Con este input se filtrará por "email" = "john@example.com", "city_name": "New York" y "departamento_id" sean "1,2,3,4 o 5"
  usando la condición *where* para los valores simples y *whereIn* para los arreglos.
*/
```
** Nota: tanto ``search`` como ``by`` pueden ir en el mismo payload de búsqueda
### En el controller
```php
$users = User::filter(User::FILTER1)->where('active', 1)->get();
```
Opcionalmente, puedes pasar el payload manualmente:
```php
$payload = ['search' => 'john']; // Request o arreglo manual

$users = User::filter(User::FILTER1, $payload)->first();
```

* Importante:
Si no se pasa la constante de filtros, ``filter()`` no buscará en ningún campo ya que este scope solo usa los campos definidos en la constante, y no buscará en otros campos.

## ``getOrPaginate()``

* Suponiendo que el input sea un json con esta estructura de datos:
```json
{
  "paginate": 10,
  "orderby": {
    "id": "ASC",
    "name": "DESC"
  }
}
```
Para la siguiente consulta se paginaría por 10 y se ordenaría por el parámetro id en ascendente y por nname en descendente una vez implementado el siguiente código:
```php
  $users = User::getOrPaginate();
```
** Nota: tanto el scope ``filter()``, como el scope ``getOrPaginate()`` pueden implementarse en la mísma consulta.

## ``customGet()`` => Este scope es la fusión de ``filter()`` y ``getOrPaginate()``
```php
$users = User::customGet(User::FILTER1);
```
** Nota: al usar este scope NO se deben usarse los otros scopes ya que este implementa tanto ``filter()`` como ``getOrPaginate()`` en uno solo.

