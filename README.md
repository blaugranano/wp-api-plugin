# [blaugranano/wp-api-plugin](https://github.com/blaugranano/wp-api-plugin)

This WordPress plugin adds custom endpoints to the WordPress REST API with API versioning and cleaner response output.

### API versioning

This plugins supports having multiple versions of the API enabled at the same time. Simply duplicate the plugin file, and change the namespace in the top of the file:

```php
<?php

namespace Blaugrana\Api\XX;
```

After enabling the plugin in the WordPress admin panel, the custom endpoint will be available at `/wp-json/bg/XX`.

### Sample request

```sh
curl -X GET 'https://api.blgr.app/wp-json/bg/v5/posts' \
  -H 'content-type: application/json' \
  -H 'accept: application/json' \
  --data-raw '{"limit":1,"offset":0,"post_status":"publish"}' \
  --compressed | json_pp -json_opt pretty
```

### Sample response

```json
{
   "_api_namespace": "Blaugrana\\Api\\v5",
   "_api_version": "v5",
   "data": {
      "next_post": {
         "post_category": "String",
         "post_id": 123,
         "post_image": "String",
         "post_slug": "String",
         "post_title": "String"
      },
      "post_author": "String",
      "post_category": "String",
      "post_content": "String",
      "post_date": "String",
      "post_id": 123,
      "post_image": "String",
      "post_slug": "String",
      "post_title": "String",
      "previous_post": {
         "post_category": "String",
         "post_id": 123,
         "post_image": "String",
         "post_slug": "String",
         "post_title": "String"
      }
   }
}
```
