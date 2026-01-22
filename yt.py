import requests
import re
import json

# The YouTube live stream URL
url = 'https://youtube.com/live/fN9uYWCjQaw'

try:
    # Fetch the page content
    response = requests.get(url)
    response.raise_for_status()
    html_content = response.text

    # Find the ytInitialPlayerResponse JSON in the HTML
    match = re.search(r'ytInitialPlayerResponse\s*=\s*(\{.*?\});', html_content, re.DOTALL)
    if match:
        player_response_str = match.group(1)
        player_response = json.loads(player_response_str)
        
        # Extract the M3U8 URL if available
        streaming_data = player_response.get('streamingData', {})
        m3u8_url = streaming_data.get('hlsManifestUrl')
        
        if m3u8_url:
            print("M3U8 URL:", m3u8_url)
        else:
            print("No M3U8 URL found in streamingData.")
    else:
        print("ytInitialPlayerResponse not found.")
except requests.RequestException as e:
    print(f"Error fetching the page: {e}")
except json.JSONDecodeError as e:
    print(f"Error parsing JSON: {e}")
