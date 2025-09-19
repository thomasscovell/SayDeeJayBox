# Playbox: An NFC Jukebox for Sonos

Playbox is a DIY project that lets you control your Sonos speakers by tapping NFC tags. It uses a Raspberry Pi with an NFC reader to create a physical interface for your digital music, inspired by the tactile nature of record players.

This project is split into two main components:
1.  **The Web Application:** A set of PHP scripts hosted on a web server that handles the secure communication with the Sonos API.
2.  **The Jukebox:** A Raspberry Pi with an NFC reader that detects taps and triggers the web application.

## Features

*   **Physical Interface:** Play music on Sonos by tapping physical objects (e.g., laminated album art with NFC stickers).
*   **Simple & Screen-Free:** Designed to be easy for anyone to use, including kids.
*   **Web-Based Management:** Easily manage your Sonos favorites and assign them to NFC tags through a web interface.
*   **Extensible:** Create tags for specific actions like stopping playback or setting the volume.
*   **Secure:** Uses the official Sonos Control API with OAuth 2.0 for secure authentication.

## How It Works

1.  **NFC Tag:** An NFC tag is programmed with a URL pointing to one of the PHP scripts on your web server (e.g., `https://your-domain.com/playbox/play.php?album=doolittle`).
2.  **Raspberry Pi Jukebox:** The Raspberry Pi runs a Python script that continuously scans for NFC tags. When a tag is tapped, the script reads the URL from it.
3.  **Webhook:** The Python script makes an HTTP GET request (a webhook) to the URL it just read.
4.  **Web Application:** The PHP script on your web server receives the request. It authenticates with the Sonos API and sends the appropriate command to your Sonos system (e.g., "play the 'Doolittle' favorite").

## Hardware Requirements

*   Raspberry Pi (tested with a Raspberry Pi 3B)
*   NFC Reader HAT for Raspberry Pi (e.g., Waveshare PN532 NFC HAT)
*   MicroSD Card (16GB or larger)
*   Power Supply for the Raspberry Pi
*   NFC Tags (NTAG213 or compatible)

### Recommended 3D-Printed Case

For a compact and tidy setup, this 3D-printed case is designed to fit the Raspberry Pi 3B+ and a POE HAT, but it also perfectly accommodates the NFC HAT mentioned above.

*   **File:** [Raspberry Pi 3B+ PoE Case on Printables.com](https://www.printables.com/model/1073303-raspberry-pi-3b-poe-case/files)

## Software Setup

This project requires setting up both a web server component and the physical Raspberry Pi jukebox.

### Part 1: Web Application Setup

The web application acts as the "brain" of the operation, handling all communication with the Sonos API.

1.  **Deploy Files:** Upload the PHP files from this repository to your web server.
2.  **Sonos Developer Account:**
    *   Create a [Sonos Developer Account](https://developer.sonos.com/).
    *   Create a new **Control Integration**.
    *   You will receive a **Client ID** and **Client Secret**.
    *   Under "Redirect URIs", add the full URL to your `callback.php` script (e.g., `https://your-domain.com/playbox/callback.php`).
3.  **Configuration (`config.php`):**
    *   Copy `config_eg.php` to `config.php`.
    *   Fill in your `SONOS_CLIENT_ID`, `SONOS_CLIENT_SECRET`, and `SONOS_REDIRECT_URI`.
    *   Define `TOKEN_FILE_PATH` to a secure, non-web-accessible location on your server where authentication tokens will be stored.
4.  **Authorize:**
    *   Open `authorize.php` in your browser. You will be redirected to Sonos to log in and grant permission.
5.  **Discover System:**
    *   Open `discover.php` in your browser to find your `SONOS_HOUSEHOLD_ID` and the names of your speakers. Update `config.php` with this information.
6.  **Manage Favorites:**
    *   Add albums/playlists to "My Sonos" in the official Sonos app.
    *   Open `list_favorites.php` in your browser. This page lets you see your Sonos Favorites and assign a short, URL-friendly alias to each one (e.g., "doolittle"). This mapping is saved in `favorites_map.json`.

For a more detailed guide, see `web_app_setup.html`.

### Part 2: Raspberry Pi Jukebox Setup

The Raspberry Pi is the physical "jukebox" that reads the NFC tags.

1.  **OS & Hardware:**
    *   Flash Raspberry Pi OS Lite (32-bit) to your microSD card.
    *   Enable SSH and configure Wi-Fi before the first boot.
    *   Attach the NFC HAT to the Pi's GPIO header.
2.  **Configuration:**
    *   Enable the serial interface using `sudo raspi-config` (`Interface Options` > `Serial Port`). Disable the login shell over serial and enable the hardware serial port.
3.  **Install Dependencies:**
    *   Connect to your Pi via SSH and install the required Python libraries:
        ```bash
        sudo apt update && sudo apt upgrade -y
        sudo apt install python3-pip
        pip3 install requests adafruit-circuitpython-pn532 --break-system-packages
        ```
4.  **Run the Script:**
    *   Copy the `jukebox.py` script to your Raspberry Pi.

5.  **Run on Boot as a Service:**
    This will ensure the Python script starts automatically when the Pi is powered on.

    1.  On the Pi, create the main application file and name it `jukebox.py`:
        ```bash
        nano jukebox.py
        ```
    2.  Copy and paste the code from the `jukebox.py` section above into the editor.
    3.  Save and exit (`Ctrl+X`, `Y`, `Enter`).
    4.  You can test the script by running it directly:
        ```bash
        python3 jukebox.py
        ```
    5.  Tapping a tag should print the URL it found and a success message. Once you've confirmed it works, stop the script with `Ctrl+C`.
    6.  Create a new service file. **Replace `pi` with your username** if you changed it.
        ```bash
        sudo nano /etc/systemd/system/jukebox.service
        ```
    7.  Copy and paste the following content. Again, **replace `pi` with your username** in the `User` and `WorkingDirectory` lines.
        ```ini
        [Unit]
        Description=Sonos NFC Jukebox Service
        After=network.target

        [Service]
        User=pi
        WorkingDirectory=/home/pi
        ExecStart=/usr/bin/python3 /home/pi/jukebox.py
        Restart=always

        [Install]
        WantedBy=multi-user.target
        ```
    8.  Save and exit (`Ctrl+X`, `Y`, `Enter`).

    **Enable and Manage the Service**

    1.  Enable the service to start on boot:
        ```bash
        sudo systemctl enable jukebox.service
        ```
    2.  Start the service now:
        ```bash
        sudo systemctl start jukebox.service
        ```
    3.  You can check its status to see if it's running correctly:
        ```bash
        sudo systemctl status jukebox.service
        ```
    4.  To see the live log output from your script, you can use:
        ```bash
        journalctl -fu jukebox.service
        ```
    5.  Press `Ctrl+C` to exit the log view.

## Programming the NFC Tags

Use a smartphone app (like NFC Tools) to write URL records to your NFC tags.

*   **Play a Favorite:** `https://your-domain.com/playbox/play.php?album=YOUR_ALIAS`
*   **Stop Playback:** `https://your-domain.com/playbox/stop.php`
*   **Set Volume:** `https://your-domain.com/playbox/volume.php?level=30` (for 30% volume)

## File Descriptions

*   `authorize.php`: Starts the OAuth 2.0 authorization flow with Sonos.
*   `callback.php`: Handles the redirect from the Sonos authorization server.
*   `token_manager.php`: Manages storing, retrieving, and refreshing the Sonos API tokens.
*   `config.php`: Main configuration file for API keys, household ID, and player names.
*   `discover.php`: A utility to discover your Sonos household ID and player names.
*   `list_favorites.php`: A web page to view your Sonos Favorites and map them to URL aliases.
*   `favorites_map.json`: Stores the mapping between your aliases and Sonos Favorite names.
*   `play.php`: The endpoint that receives a webhook to play a specific favorite.
*   `stop.php`: The endpoint that receives a webhook to stop playback.
*   `volume.php`: The endpoint that receives a webhook to set the volume.
*   `jukebox.py`: The Python script that runs on the Raspberry Pi to read NFC tags.
*   `pi_setup.html`: Detailed instructions for setting up the Raspberry Pi hardware and software.
*   `web_app_setup.html`: Detailed instructions for setting up the web application.

## Future Improvements

*   Add support for physical stop and volume buttons.
*   Add light and buzzer support to notify of successful NFC taps prior to music starting.
*   Add LCD screen support for "now playing" information.

## License

This project is licensed under the GPL-3.0 License. See the `LICENSE` file for details.