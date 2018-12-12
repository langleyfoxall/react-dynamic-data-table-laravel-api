# Laravel API for React Dynamic Data Table

This package provides a Laravel API endpoint responder for the 
[React Dynamic Data Table](https://github.com/langleyfoxall/react-dynamic-data-table) 
component.

## Installation

```bash
composer require langleyfoxall/react-dynamic-data-table-laravel-api
```

## Usage

First, create a new route in your API routes file for the data table response, and point it to a controller.

In this controller method, create a new `DataTableResponder` passing it the model you wish to return data about, and the provided instance of the `Request` object. You can optionally specify changes to the query (such as sorting, or filtering) using the `query` method. If you want to alter the data before it gets return, such as loading relationships or appending custom attributes you can take advantage of `collectionManipulator`. You can also change number of records shown per page with the `setPerPage` method.

See the example usage below.

```php
use App\User;
use Illuminate\Http\Request;
use LangleyFoxall\ReactDynamicDataTableLaravelApi\DataTableResponder;

class UsersController extends Controller
{
    public function dataTable(Request $request)
    {
        return (new DataTableResponder(User::class, $request))
            ->query(function($query) {                                   // Optional, default: none
                $query->where('name', 'like', 'B%');
            })
            ->collectionManipulator(function (Collection $collection) {  // Optional, default: none
                $collection->map(function($user) {
                    $user->name = title_case($user->name);
                });
            })
            ->setPerPage(10)                                             // Optional, default: 15
            ->respond();
    }
}
```

In your frontend code, you can now use the [React Dynamic Data Table](https://github.com/langleyfoxall/react-dynamic-data-table) package's `AjaxDynamicDataTable` component to display a table of this data. The API route previously defined should be passed to this component as the `apiUrl` prop.

An example usage is shown below.

```jsx
import React, { Component } from 'react';
import ReactDOM from 'react-dom';
import AjaxDynamicDataTable from "@langleyfoxall/react-dynamic-data-table/dist/AjaxDynamicDataTable";

export default class Example extends Component {
    render() {
        return (
            <div className="container">
                <AjaxDynamicDataTable apiUrl={'/api/users/data-table'}/>
            </div>
        );
    }
}

if (document.getElementById('example')) {
    ReactDOM.render(<Example />, document.getElementById('example'));
}
```
