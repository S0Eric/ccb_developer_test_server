# Back-End Developer Test

## Background
This was an interesting exercise. This would have been easy for me to implement with the
.NET or .NET Core frameworks, so I decided to do it in PHP instead.

PHP is completely new to me and I didn't know a thing about it, but the language is
powerful and was fun to learn. I did struggle with a few things, mostly because of
outdated posts, but the language and APIs (like SLIM) seems to have kept up with the times.

I'm sure there are a number of places where I didn't use the most idiomatic approach.
Error handling is something I typically focus on a lot, but in this case I took some
shortcuts.

For example, there is no error handling in the database queries, but as long as the DB
is accessible, and the config file is correct, it shouldn't throw any errors. All the
SQL uses named parameters, so user input can't cause exceptions or security issues.

Exceptions cause a 500 status, unless the ENVIRONMENT variable is set to DEVELOPMENT,
and then exception details are shown:
```
> set ENVIRONMENT=DEVELOPMENT
> php -S localhost:8080
```

## Approach
Because a typical REST API is going to expose many tables, I decided to make
it interesting by making it data driven. The **api.json** file contains the data that
drives the endpoint definitions and the logic that retrieves the data. More tables
and data can be exposed, and filters implemented, without having to make code changes.

Here are the contents of the current **api.json** file:
```
{
  "tables": [
    {
      "path": "movies",
      "detail-path": "movie",
      "table": "film",
      "sql": "select f.*, c.name as category from film as f left join film_category as fc on fc.film_id = f.film_id left join category as c on c.category_id = fc.category_id",
      "filters": [
        {
          "param": "t",
          "field": "title",
          "match-type": "contains"
        },
        {
          "param": "c",
          "field": "c.name"
        },
        {
          "param": "r",
          "field": "rating"
        }
      ],
      "children": [
        {
          "table": "actor",
          "xname": "actors",
          "sql": "select a.* from film_actor as fa inner join actor as a on a.actor_id = fa.actor_id where fa.film_id = :parent_id"
        }
      ]
    },
    {
      "path": "movie-actors",
      "detail-path": "actor",
      "table": "actor",
      "sql": "select fa.film_id, a.* from film_actor as fa inner join actor as a on a.actor_id = fa.actor_id",
      "filters": [
        {
          "param": "mid",
          "field": "fa.film_id"
        },
        {
          "param": "aid",
          "field": "a.actor_id"
        }
      ]
    }
  ]
}
```

The above config file exposes these endpoints:

| Path    | Options | Description |
| ------- | ------- | ----------- |
| /movies | t=title, c=category, r=rating | Shows a list of movies with optional filtering |
| /movie/\{id} | none | Shows details for a specific movie. A collection of actors is returned as well. |
| /movie-actors | mid=film_id, aid=actor_id | This supports listing all actors in a movie with the **mid** option. It can also list all movies an actor is in with the **aid** filter. |
| /actor/\{id} | none | Shows details for a specific actor. |

Each *table* entry exposes an endpoint for a list of data as well as a detailed single-row. The **path** field defines
the list endpoint path, and the **detail-path** field defines the detail endpoint path. **table** defines the name of
the DB table. **sql** defines the list SQL (defaults to 'select * from \{table}'). **filters** optionally defines the
filters that are supported. **children** optionally defines child collections that will be included in a detail request.

The second "movie-actors" entry is a little strange because the list SQL joins with the **film_actor** table to
support listing all actors that are in a movie, but the detail endpoint simply returns details for a single actor.

Here are some sample URLs:

[All movies with "QU" in the title - http://localhost:8080/movies?t=qu](http://localhost:8080/movies?t=qu)

[Same as above, but rated PG - http://localhost:8080/movies?t=qu&r=pg](http://localhost:8080/movies?t=qu&r=pg)

[Details for movie with film_id=1 - http://localhost:8080/movie/1](http://localhost:8080/movie/1)

[List of actors in movie with film_id=1 - http://localhost:8080/movie-actors?mid=1](http://localhost:8080/movie-actors?mid=1)

[Details for actor with actor_id=1 - http://localhost:8080/actor/1](http://localhost:8080/actor/1)


