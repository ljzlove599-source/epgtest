#!/usr/bin/env python3
"""
从指定URL提取m3u8链接并生成播放列表
"""

import requests
import re
import json
from urllib.parse import urljoin, urlparse
import base64
from bs4 import BeautifulSoup
import sys
import time

class M3U8Extractor:
    def __init__(self, url):
        self.url = url
        self.session = requests.Session()
        self.session.headers.update({
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language': 'en-US,en;q=0.5',
            'Accept-Encoding': 'gzip, deflate',
            'Connection': 'keep-alive',
            'Upgrade-Insecure-Requests': '1',
        })
        
    def extract_links(self):
        """从页面提取m3u8链接"""
        m3u8_links = set()
        
        try:
            print(f"Fetching: {self.url}")
            response = self.session.get(self.url, timeout=30)
            response.raise_for_status()
            
            content = response.text
            content_type = response.headers.get('Content-Type', '')
            
            print(f"Content-Type: {content_type}")
            print(f"Content length: {len(content)} chars")
            
            # 方法1: 直接正则匹配
            patterns = [
                r'(https?://[^\s"\']+\.m3u8[^\s"\']*)',
                r'(http[^\s"\']*\.m3u8)',
                r'src=["\']([^"\']+\.m3u8)["\']',
                r'["\'](https?://[^"\']+\.m3u8)["\']',
                r'stream[\s:=]+["\']([^"\']+\.m3u8)["\']',
                r'url["\']?\s*:\s*["\']([^"\']+\.m3u8)["\']',
                r'file["\']?\s*:\s*["\']([^"\']+\.m3u8)["\']',
            ]
            
            for pattern in patterns:
                matches = re.findall(pattern, content, re.IGNORECASE)
                for match in matches:
                    full_url = urljoin(self.url, match)
                    m3u8_links.add(full_url)
            
            # 方法2: 从JSON数据中提取
            json_patterns = [
                r'\{[^{}]*\.m3u8[^{}]*\}',
                r'\[[^\[\]]*\.m3u8[^\[\]]*\]',
            ]
            
            for pattern in json_patterns:
                json_matches = re.findall(pattern, content)
                for json_str in json_matches:
                    try:
                        data = json.loads(json_str)
                        self._extract_from_json(data, m3u8_links)
                    except:
                        pass
            
            # 方法3: 解析HTML
            if 'text/html' in content_type:
                soup = BeautifulSoup(content, 'html.parser')
                
                # 查找所有可能的标签
                tags_to_check = ['a', 'link', 'script', 'iframe', 'source', 'video', 'audio']
                
                for tag_name in tags_to_check:
                    for tag in soup.find_all(tag_name, src=True):
                        src = tag.get('src', '')
                        if '.m3u8' in src:
                            full_url = urljoin(self.url, src)
                            m3u8_links.add(full_url)
                
                # 检查href属性
                for tag in soup.find_all(href=True):
                    href = tag.get('href', '')
                    if '.m3u8' in href:
                        full_url = urljoin(self.url, href)
                        m3u8_links.add(full_url)
                
                # 检查script标签内容
                for script in soup.find_all('script'):
                    if script.string:
                        script_matches = re.findall(r'https?://[^\s"\']+\.m3u8', script.string)
                        for match in script_matches:
                            full_url = urljoin(self.url, match)
                            m3u8_links.add(full_url)
            
            # 方法4: 尝试解码base64
            base64_patterns = re.findall(r'[A-Za-z0-9+/=]{20,}', content)
            for b64_str in base64_patterns:
                try:
                    decoded = base64.b64decode(b64_str).decode('utf-8', errors='ignore')
                    b64_matches = re.findall(r'https?://[^\s"\']+\.m3u8', decoded)
                    for match in b64_matches:
                        full_url = urljoin(self.url, match)
                        m3u8_links.add(full_url)
                except:
                    pass
            
            return list(m3u8_links)
            
        except Exception as e:
            print(f"Error extracting links: {e}")
            return []
    
    def _extract_from_json(self, data, links_set):
        """递归从JSON数据中提取m3u8链接"""
        if isinstance(data, dict):
            for key, value in data.items():
                if isinstance(value, str) and '.m3u8' in value:
                    full_url = urljoin(self.url, value)
                    links_set.add(full_url)
                elif isinstance(value, (dict, list)):
                    self._extract_from_json(value, links_set)
        elif isinstance(data, list):
            for item in data:
                if isinstance(item, str) and '.m3u8' in item:
                    full_url = urljoin(self.url, item)
                    links_set.add(full_url)
                elif isinstance(item, (dict, list)):
                    self._extract_from_json(item, links_set)

def generate_m3u_playlist(links, output_file='live.m3u'):
    """生成M3U播放列表"""
    if not links:
        print("No m3u8 links found to generate playlist!")
        return False
    
    playlist = "#EXTM3U x-tvg-url=\"\"\n\n"
    
    for idx, link in enumerate(links, 1):
        # 提取频道名称
        parsed_url = urlparse(link)
        path_parts = parsed_url.path.split('/')
        
        # 尝试从URL中获取有意义的名称
        channel_name = f"Channel_{idx}"
        for part in reversed(path_parts):
            if part and '.m3u8' not in part and len(part) > 2:
                channel_name = part.replace('-', ' ').replace('_', ' ').title()
                break
        
        # 清理名称
        channel_name = re.sub(r'[^\w\s-]', '', channel_name)
        
        # 生成频道条目
        playlist += f'#EXTINF:-1 tvg-id="channel{idx}" tvg-name="{channel_name}" tvg-logo="" group-title="Live", {channel_name}\n'
        playlist += f'{link}\n\n'
    
    # 写入文件
    with open(output_file, 'w', encoding='utf-8') as f:
        f.write(playlist)
    
    print(f"Generated playlist with {len(links)} channels")
    print(f"Output file: {output_file}")
    
    return True

def main():
    target_url = "https://litv.msbot.dpdns.org/test.php"
    
    # 创建提取器
    extractor = M3U8Extractor(target_url)
    
    # 提取链接
    print("Extracting m3u8 links...")
    m3u8_links = extractor.extract_links()
    
    print(f"\nFound {len(m3u8_links)} unique m3u8 links:")
    for i, link in enumerate(m3u8_links, 1):
        print(f"{i:3d}. {link}")
    
    # 生成播放列表
    if m3u8_links:
        success = generate_m3u_playlist(m3u8_links)
        if success:
            print("\nSuccessfully generated live.m3u file!")
        else:
            print("\nFailed to generate playlist!")
            sys.exit(1)
    else:
        print("\nNo m3u8 links found!")
        sys.exit(1)

if __name__ == "__main__":
    main()
