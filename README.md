# Laravel API for React Dynamic Data Table

*Work in progress*

This package provides a Laravel API endpoint responder for the 
[React Dynamic Data Table](https://github.com/langleyfoxall/react-dynamic-data-table) 
component.

## Installation

```bash
composer require langleyfoxall/react-dynamic-data-table-laravel-api
```

## Usage

Example syntax:

```php
use App\User;
use Illuminate\Http\Request;
use LangleyFoxall\ReactDynamicDataTableLaravelApi\DataTableResponder;

class UsersController extends Controller
{
	public function dataTable(Request $request)
	{
		return (new DataTableResponder(User::class, $request))
			->setPerPage(10)    // Optional, default: 15
			->respond();
	}
}
```
