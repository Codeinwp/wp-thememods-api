# wp-thememods-api

Helper plugin which allows setting up theme mods via REST API.

If you would like to restrict access to the endpoing you can define this constant `WPTHEMEMODS_SECRET` 
with a passkey which can be sent along with the request as Bearer token.

The endpoint is available at `wpthememods/v1/settings` and it receives a JSON payload with the theme mods to set. 


**This plugin should not be used on production environments.**  

