# MediaU server

Many internet radios are based on Magic Systech modules, which connect to MediaU web portal to retrieve channel logos, audio stream URLs, and other information, and track your listening habits. In 2021, Magic Systech was acquired by another internet radio technology company, Frontier Smart Technologies. They shut down the MediaU portal in late 2024 and replaced it with [Airable](https://www.airable.fm/mediau).

This MediaU server project is a self-hosted portal that provides the same functionality to radios ***locally***, without sharing your listening habits with anyone, and without losing features if the Airable service gets shut down too.


## Features

* Customizable channel logos: you can replace logos retrieved from Airable with your own.

* Customizable stream URLs: you can override URLs retrieved from Airable with alternate feeds, for example, to replace a medium-quality feed with high-quality stream.

* Album artwork: cover images are retrieved from iTunes and processed into a suitable format for displaying on screens. This functionality was previously present in radios, but got broken after iTunes moved to HTTPS, and was never fixed.

* Locally-managed favorites: you can add favorites through a JSON file without needing any external services.

* Local cache: all information retrieved from Airable is cached locally to ensure that the radio stays fully functional even if all external services disappear.


## How it works

Radios make unencrypted HTTP calls to MediaU service. If we assign radios a DNS server that tells them that mediayou.net and other MediaU domains are located on our server, radios will connect to our instance instead of the actual MediaU service. This allows our server to listen to incoming requests and send custom responses back to radios without relying on the availability of external services.


## Requirements

You need a device running Linux and a router capable of assigning custom DNS server to select network devices.

The instructions below are for Debian v12.9.0 installed into a virtual machine, on a network connected by a Mikrotik router running RouterOS v6.48.6. The same steps -- with minimal changes -- apply to many other systems too.


## Installation


### 1. Configure static IPs and DNS assignment

First of all, we assign static IP to our Debian host. Radios need a static IP as well.

Assuming that the Debian host has MAC address `AA:BB:CC:DD:EE:FF` and the radio has `AA:BB:CC:DD:EE:00` and we want to assign them IPs `192.168.1.10` and `192.168.1.11` respectively, we would need to enter this into RouterOS:
```
/ip dhcp-server lease set [find mac-address=AA:BB:CC:DD:EE:FF] address=192.168.1.10
/ip dhcp-server lease set [find mac-address=AA:BB:CC:DD:EE:00] address=192.168.1.11
```

We also need to override the DNS server assigned to the radio. We can do it creating a `DHCP option 6` entry and assigning it to the radio:
```
/ip dhcp-server option add name=mediau-dns code=6 value="'192.168.1.10'"
/ip dhcp-server lease set [find mac-address=AA:BB:CC:DD:EE:FF] dhcp-option=mediau-dns
```

If your router does not allow assigning DHCP options, an alternative to this is creating a new subnet and making the Debian host the default DNS server for the entire subnet.


### 2. Install dependencies

Log into Debian.

You need:
* nginx to host a local web server,
* dnsmasq to run a DNS server that redirects domains to the web server,
* PHP with curl, xml and ImageMagick support to make external HTTP calls, work with XML files, and process JPEG images.
* git to copy this repository.

So let's install them:
```
sudo apt update
sudo apt install nginx dnsmasq imagemagick php-fpm php-curl php-xml php-imagick git -y
```


### 3. Download mediau-server contents


Remove placeholder index page, clone this repository into the root document folder, and grant the web server access to the files:
```
sudo rm /var/www/html/index.nginx-debian.html
sudo git clone --branch master --single-branch https://github.com/paavoph/mediau-server.git /var/www/html
sudo chown -R www-data:www-data /var/www/html
```


### 4. Configure nginx

Disable the default configuration:
```
sudo rm /etc/nginx/sites-enabled/default
```

Deploy nginx configuration file and make it active:
```
sudo cp /var/www/html/examples/nginx.conf /etc/nginx/sites-available/mediau-server.conf
sudo ln -s /etc/nginx/sites-available/mediau-server.conf /etc/nginx/sites-enabled/mediau-server.conf
```

Restart nginx:
```
sudo systemctl restart nginx
```


### 5. Configure dnsmasq

Deploy dnsmasq configuration file:
```
sudo cp /var/www/html/examples/dnsmasq.conf /etc/dnsmasq.d/mediau-server.conf
```

Open it with an editor and change `192.168.1.10` in the configuration file to the IP address of the Debian host if yours is different:
```
sudo nano /etc/dnsmasq.d/mediau-server.conf
```

Finally, restart dnsmasq:
```
sudo systemctl restart dnsmasq
```

### 6. Reboot radio

Completely power off and power on your radio.

This concludes the installation. Debian and radio have static IPs. Router tells the radio to contact Debian for address resolution, and Debian responds that requests to MediaU domains should be redirected to Debian. When Debian sees a MediaU request, it uses the installed `mediau-server` scripts to respond.


## Tips

### Web UI and logos

Open `http://192.168.1.10` (your Debian's IP) in your browser. You will see a list of cached channel logos. A handful are provided with this repository, others are added as you switch through channels. Each entry shows an ID. You can matching logos with matching IDs in `/var/www/html/cache/logo` folder.

Logos must be baseline JPEG (not progressive), use H1V1 (4:4:4 chroma subsampling), and not include any metadata or ICC profiles. If you see diagonal swirling artifacts, then that indicates a problem with the JPEG format.


### Stream URLs

Stream URLs can be overriden with `/var/www/html/conf/streams.json` file:

```
{
  "2767": {
    "title": "SoCal Sound",
    "url": "https://stream1.thesocalsound.org/1"
  },
  "7670": {
    "title": "Vikerraadio",
    "url": "http://icecast.err.ee/vikerraadiokorge.mp3"
  }
}
```
Numeric ID is the same as shown in Web UI.


### Favorites

Favorites can be set with `/var/www/html/conf/favorites.json` file:

```
{
  "Local": {
    "Vikerraadio": "http://icecast.err.ee/vikerraadiokorge.mp3"
  },
  "US stations": {
    "SoCal Sound": "https://stream1.thesocalsound.org/1",
    "KEXP": "https://kexp.streamguys1.com/kexp160.aac"
  },
  "Air traffic": {
    "Tallinn Tower": "https://www.liveatc.net/play/eetn2_twr.pls",
    "Tallinn Radar": "https://www.liveatc.net/play/eetn2_radar.pls",
    "Tallinn ATIS": "https://www.liveatc.net/play/eetn2_atis.pls"
  }
}
```

It is a simple two-level hierarchy where the first level is folder name and the second level is stream name, followed by URL. Keep the number or favorites less than 250 per folder. Radios may struggle if menus are longer than that.


### Other options

Other configurations options can be found inside `/var/www/html/settings.php`.
