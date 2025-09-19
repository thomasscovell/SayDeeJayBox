import requests
import time
import serial
from adafruit_pn532.uart import PN532_UART

def parse_ndef_uri(data: bytearray) -> str | None:
    # This function defines how to parse the URL.
    # It must come before it is used.
    uri_prefixes = {
        0x00: "", 0x01: "http://www.", 0x02: "https://www.", 0x03: "http://",
        0x04: "https://", 0x05: "tel:", 0x06: "mailto:", 0x07: "ftp://anonymous:anonymous@",
        0x08: "ftp://ftp.", 0x09: "ftps://", 0x0A: "sftp://", 0x0B: "smb://",
        0x0C: "nfs://", 0x0D: "ftp://", 0x0E: "dav://", 0x0F: "news:",
        0x10: "telnet://", 0x11: "imap:", 0x12: "rtsp://", 0x13: "urn:",
        0x14: "pop:", 0x15: "sip:", 0x16: "sips:", 0x17: "tftp:",
        0x18: "btspp://", 0x19: "btl2cap://", 0x1A: "btgoep://", 0x1B: "tcpobex://",
        0x1C: "irdaobex://", 0x1D: "file://", 0x1E: "urn:epc:id:", 0x1F: "urn:epc:tag:",
        0x20: "urn:epc:pat:", 0x21: "urn:epc:raw:", 0x22: "urn:epc:", 0x23: "urn:nfc:",
    }
    try:
        uri_marker_index = data.find(b'U') # 'U' for URI
        if uri_marker_index == -1:
            return None
        payload_length = data[uri_marker_index - 1]
        prefix_code = data[uri_marker_index + 1]
        url_body = data[uri_marker_index + 2 : uri_marker_index + 1 + payload_length]
        prefix = uri_prefixes.get(prefix_code, "")
        full_url = prefix + url_body.decode('utf-8')
        return full_url
    except Exception:
        return None

# --- NFC Setup ---
uart = serial.Serial("/dev/ttyS0", baudrate=1152200, timeout=0.1)
pn532 = PN532_UART(uart, debug=False)
pn532.SAM_configuration()
print("Sonos NFC Jukebox is running...")

# --- Main Loop ---
while True:
    try:
        uid = pn532.read_passive_target(timeout=0.5)
        if uid is None:
            continue

        print(f"\nTag found! UID: {[hex(i) for i in uid]}")
        print("Manually reading pages 4-36...")
        
        all_data = bytearray()
        for page in range(4, 36):
            try:
                page_data = pn532.ntag2xx_read_block(page)
                if page_data:
                    all_data.extend(page_data)
                else:
                    break
            except Exception:
                break
        
        terminator = all_data.find(b'\xfe')
        if terminator != -1:
            all_data = all_data[:terminator]
            
        # The call to the function happens HERE, inside the loop
        url = parse_ndef_uri(all_data)

        if url:
            # The inspection and cleaning happens HERE
            print(f"Inspecting URL: {repr(url)}")      ## <--- HERE is the inspection line
            url = url.strip().replace('\x00', '')   ## <--- HERE is the cleaning line

            print(f"Found URL: {url}")
            print("Triggering webhook...")
            try:
                response = requests.get(url, timeout=10)
                print("Webhook request completed.")
                print(f"Webhook response: {response.status_code}")
            except requests.exceptions.RequestException as e:
                print(f"Error calling webhook: {e}")
            finally:
                print("--- Webhook action finished ---")
        else:
            print("Could not find a valid NDEF URI record on the tag.")

        time.sleep(3)

    except Exception as e:
        print(f"An unhandled error occurred: {e}")
        pn532.SAM_configuration()
        time.sleep(5)