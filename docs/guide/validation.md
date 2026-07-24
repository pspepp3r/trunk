# Validation

Trunk validates requests through `FormRequest` subclasses rather than manual `if` checks in controllers.

## Defining a request

```php
namespace App\Requests;

use Trunk\Validation\FormRequest;

class CreateUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
        ];
    }
}
```

Type-hint it as a controller's first parameter and Trunk validates it automatically before your handler runs (see [Routing](/guide/routing)):

```php
public function create(CreateUserRequest $request): PromiseInterface
{
    $data = $request->validated(); // only the fields declared in rules()
    // ...
}
```

A failed validation returns a `422`:

```json
{
  "error": "Validation Failed",
  "errors": {
    "email": ["The email field must be a valid email address."]
  }
}
```

## Rule reference

Rules are pipe-delimited strings per field:

| Rule | Behavior |
|---|---|
| `required` | Fails if the field is missing, `null`, or an empty string. |
| `nullable` | If the field is absent, skips every other rule for it. |
| `string` | Must be a string. |
| `integer` / `int` | Must be an int, or a numeric string of digits. |
| `numeric` | Must pass `is_numeric()`. |
| `boolean` / `bool` | Must be `true`, `false`, `0`, `1`, `'0'`, or `'1'`. |
| `array` | Must be an array. |
| `email` | Must pass `FILTER_VALIDATE_EMAIL`. |
| `min:N` | String length (or numeric value) must be at least `N`. |
| `max:N` | String length (or numeric value) must not exceed `N`. |
| `in:a,b,c` | Value must be one of the comma-separated options. |

## Outside the router

You can validate manually anywhere without going through a route:

```php
use Trunk\Validation\Validator;

$validator = Validator::make($data, ['email' => 'required|email']);

if ($validator->fails()) {
    $errors = $validator->errors(); // array<string, string[]>
}
```

## Reading other request data

`FormRequest` also exposes the raw input and the underlying PSR-7 request:

```php
$request->input('name', 'default');     // any field, validated or not
$request->getServerRequest();           // the original ServerRequestInterface
```
