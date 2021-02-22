# wp-thememods-api

Helper plugin which allows setting up theme mods via REST API.

#### Configuration
If you would like to restrict access to the endpoint you can define this constant `WPTHEMEMODS_SECRET` 
with a passkey which can be sent along with the request as Bearer token. 
When the constant is not present the endpoint has public access.

#### How to use
The endpoint is available at `wpthememods/v1/settings` and it receives a POST request with a JSON payload containg the theme mods to set. 

#### Sample Code
```js
var myHeaders = new Headers();
myHeaders.append("Content-Type", "application/json");

var raw = JSON.stringify({"neve_default_sidebar_layout":"full-width"});

var requestOptions = {
method: 'POST',
headers: myHeaders,
body: raw,
redirect: 'follow'
};

fetch("<siteurl>/wp-json/wpthememods/v1/settings", requestOptions)
.then(response => response.text())
.then(result => console.log(result))
.catch(error => console.log('error', error));
```

#### Disclaimer

**This plugin should not be used on production environments.**  

