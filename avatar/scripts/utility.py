#!/usr/bin/env python3
"""
Complete System Utilities Script
Provides comprehensive system monitoring and FFmpeg availability checking.
"""

import subprocess
import shutil
import os
import sys
import platform
import time
from datetime import datetime
from pathlib import Path
import unicodedata
import requests
import cv2
import numpy as np
import base64

# Third-party imports (install with: pip install psutil)
try:
    import psutil

    PSUTIL_AVAILABLE = True
except ImportError:
    PSUTIL_AVAILABLE = False
    print("Warning: psutil not available. Install with: pip install psutil")


# ============================================================================
# UTILITY FUNCTIONS
# ============================================================================

def clean_text(text: str) -> str:
    """Normalize and sanitize input text before TTS"""
    if not text:
        return "Hello"
    text = unicodedata.normalize("NFKC", text)
    text = text.strip()
    text = " ".join(text.split())
    text = "".join(ch for ch in text if ch.isprintable())
    return text




ref_image_path = '/app/faces/2.jpg'
def load_and_preprocess_image(img_input, fallback=ref_image_path):
    """Load image from path, base64, or URL, then preprocess for face detection"""


    img = None
    try:
        if img_input and isinstance(img_input, str):
            if img_input.startswith("http"):
                resp = requests.get(img_input, timeout=10)
                img_arr = np.frombuffer(resp.content, np.uint8)
                img = cv2.imdecode(img_arr, cv2.IMREAD_COLOR)
            elif os.path.exists(img_input):
                img = cv2.imread(img_input)
            else:
                # Try base64
                try:
                    img_data = base64.b64decode(img_input)
                    img_arr = np.frombuffer(img_data, np.uint8)
                    img = cv2.imdecode(img_arr, cv2.IMREAD_COLOR)
                except Exception:
                    pass
        # fallback
        if img is None and fallback and os.path.exists(fallback):
            img = cv2.imread(fallback)

        if img is not None:
            # Resize for consistency
            img = cv2.resize(img, (512, 512))
            # Optional: denoise / normalize
            img = cv2.fastNlMeansDenoisingColored(img, None, 10, 10, 7, 21)
        return img
    except Exception as e:
        print(f"Image load error: {e}")
        return cv2.imread(fallback) if os.path.exists(fallback) else None







def check_ffmpeg_available():
    """
    Check if FFmpeg is available on the system

    Returns:
        dict: Dictionary containing availability status and version info
    """
    try:
        result = subprocess.run(
            ['ffmpeg', '-version'],
            capture_output=True,
            text=True,
            timeout=10
        )

        if result.returncode == 0:
            # Extract version from output
            version_line = result.stdout.split('\n')[0]
            return {
                'available': True,
                'version': version_line,
                'path': shutil.which('ffmpeg')
            }
        else:
            return {
                'available': False,
                'error': result.stderr,
                'path': None
            }
    except subprocess.TimeoutExpired:
        return {
            'available': False,
            'error': 'FFmpeg check timed out',
            'path': None
        }
    except FileNotFoundError:
        return {
            'available': False,
            'error': 'FFmpeg not found in PATH',
            'path': None
        }
    except Exception as e:
        return {
            'available': False,
            'error': str(e),
            'path': None
        }


def get_disk_usage(path='/'):
    """
    Get disk usage information for a specified path

    Args:
        path (str): Path to check disk usage for (default: root)

    Returns:
        dict: Dictionary containing disk usage statistics
    """
    try:
        # Use the current directory if /app doesn't exist
        if not os.path.exists(path):
            if os.name == 'nt':  # Windows
                path = 'C:\\'
            else:  # Unix-like systems
                path = '/'

        total, used, free = shutil.disk_usage(path)

        return {
            'path': path,
            'total_gb': round(total / (1024 ** 3), 2),
            'used_gb': round(used / (1024 ** 3), 2),
            'free_gb': round(free / (1024 ** 3), 2),
            'usage_percent': round((used / total) * 100, 1),
            'total_bytes': total,
            'used_bytes': used,
            'free_bytes': free
        }
    except Exception as e:
        return {
            'error': str(e),
            'path': path,
            'status': 'Unknown'
        }


def get_memory_info():
    """
    Get memory usage information

    Returns:
        dict: Dictionary containing memory statistics
    """
    if not PSUTIL_AVAILABLE:
        return {
            'error': 'psutil not available',
            'status': 'Unknown'
        }

    try:
        memory = psutil.virtual_memory()
        swap = psutil.swap_memory()

        return {
            'virtual_memory': {
                'total_gb': round(memory.total / (1024 ** 3), 2),
                'available_gb': round(memory.available / (1024 ** 3), 2),
                'used_gb': round(memory.used / (1024 ** 3), 2),
                'free_gb': round(memory.free / (1024 ** 3), 2),
                'usage_percent': memory.percent,
                'cached_gb': round(memory.cached / (1024 ** 3), 2) if hasattr(memory, 'cached') else 0,
                'buffers_gb': round(memory.buffers / (1024 ** 3), 2) if hasattr(memory, 'buffers') else 0
            },
            'swap_memory': {
                'total_gb': round(swap.total / (1024 ** 3), 2),
                'used_gb': round(swap.used / (1024 ** 3), 2),
                'free_gb': round(swap.free / (1024 ** 3), 2),
                'usage_percent': swap.percent
            }
        }
    except Exception as e:
        return {
            'error': str(e),
            'status': 'Unknown'
        }


def get_cpu_info():
    """
    Get CPU information and usage statistics

    Returns:
        dict: Dictionary containing CPU information
    """
    if not PSUTIL_AVAILABLE:
        return {
            'error': 'psutil not available',
            'status': 'Unknown'
        }

    try:
        cpu_percent = psutil.cpu_percent(interval=1, percpu=True)
        cpu_freq = psutil.cpu_freq()

        return {
            'physical_cores': psutil.cpu_count(logical=False),
            'total_cores': psutil.cpu_count(logical=True),
            'current_frequency_mhz': round(cpu_freq.current, 2) if cpu_freq else 'Unknown',
            'min_frequency_mhz': round(cpu_freq.min, 2) if cpu_freq else 'Unknown',
            'max_frequency_mhz': round(cpu_freq.max, 2) if cpu_freq else 'Unknown',
            'cpu_usage_percent': round(sum(cpu_percent) / len(cpu_percent), 1),
            'per_core_usage': [round(usage, 1) for usage in cpu_percent],
            'load_average': os.getloadavg() if hasattr(os, 'getloadavg') else 'Unknown'
        }
    except Exception as e:
        return {
            'error': str(e),
            'status': 'Unknown'
        }


def get_system_info():
    """
    Get general system information

    Returns:
        dict: Dictionary containing system information
    """
    try:
        boot_time = datetime.fromtimestamp(psutil.boot_time()) if PSUTIL_AVAILABLE else 'Unknown'

        return {
            'platform': platform.platform(),
            'system': platform.system(),
            'node': platform.node(),
            'release': platform.release(),
            'version': platform.version(),
            'machine': platform.machine(),
            'processor': platform.processor(),
            'python_version': platform.python_version(),
            'boot_time': boot_time.strftime('%Y-%m-%d %H:%M:%S') if boot_time != 'Unknown' else 'Unknown',
            'current_time': datetime.now().strftime('%Y-%m-%d %H:%M:%S')
        }
    except Exception as e:
        return {
            'error': str(e),
            'status': 'Unknown'
        }


def get_network_info():
    """
    Get network interface information

    Returns:
        dict: Dictionary containing network statistics
    """
    if not PSUTIL_AVAILABLE:
        return {
            'error': 'psutil not available',
            'status': 'Unknown'
        }

    try:
        net_io = psutil.net_io_counters()
        net_interfaces = psutil.net_if_addrs()

        return {
            'io_counters': {
                'bytes_sent': net_io.bytes_sent,
                'bytes_recv': net_io.bytes_recv,
                'packets_sent': net_io.packets_sent,
                'packets_recv': net_io.packets_recv,
                'errors_in': net_io.errin,
                'errors_out': net_io.errout,
                'drops_in': net_io.dropin,
                'drops_out': net_io.dropout
            },
            'interfaces': list(net_interfaces.keys()),
            'interface_count': len(net_interfaces)
        }
    except Exception as e:
        return {
            'error': str(e),
            'status': 'Unknown'
        }


def get_process_info(limit=10):
    """
    Get information about running processes

    Args:
        limit (int): Number of top processes to return

    Returns:
        dict: Dictionary containing process information
    """
    if not PSUTIL_AVAILABLE:
        return {
            'error': 'psutil not available',
            'status': 'Unknown'
        }

    try:
        processes = []
        for proc in psutil.process_iter(['pid', 'name', 'cpu_percent', 'memory_percent']):
            try:
                processes.append(proc.info)
            except (psutil.NoSuchProcess, psutil.AccessDenied):
                pass

        # Sort by CPU usage
        processes.sort(key=lambda x: x['cpu_percent'] or 0, reverse=True)

        return {
            'total_processes': len(processes),
            'top_processes_by_cpu': processes[:limit],
            'current_process_pid': os.getpid()
        }
    except Exception as e:
        return {
            'error': str(e),
            'status': 'Unknown'
        }


def generate_system_report():
    """
    Generate a comprehensive system report

    Returns:
        dict: Complete system report
    """
    print("Generating system report...")

    report = {
        'timestamp': datetime.now().isoformat(),
        'ffmpeg': check_ffmpeg_available(),
        'disk_usage': get_disk_usage(),
        'memory': get_memory_info(),
        'cpu': get_cpu_info(),
        'system': get_system_info(),
        'network': get_network_info(),
        'processes': get_process_info()
    }

    return report


def print_system_report(report=None):
    """
    Print a formatted system report

    Args:
        report (dict): System report dictionary (if None, generates new report)
    """
    if report is None:
        report = generate_system_report()

    print("\n" + "=" * 80)
    print("SYSTEM REPORT")
    print("=" * 80)
    print(f"Generated at: {report['timestamp']}")
    print()

    # FFmpeg Status
    print("FFmpeg Status:")
    ffmpeg = report['ffmpeg']
    if ffmpeg['available']:
        print(f"  ✓ Available: {ffmpeg['version']}")
        print(f"  Path: {ffmpeg['path']}")
    else:
        print(f"  ✗ Not Available: {ffmpeg['error']}")
    print()

    # System Information
    print("System Information:")
    system = report['system']
    if 'error' not in system:
        print(f"  Platform: {system['platform']}")
        print(f"  Python Version: {system['python_version']}")
        print(f"  Boot Time: {system['boot_time']}")
    else:
        print(f"  Error: {system['error']}")
    print()

    # Disk Usage
    print("Disk Usage:")
    disk = report['disk_usage']
    if 'error' not in disk:
        print(f"  Path: {disk['path']}")
        print(f"  Total: {disk['total_gb']} GB")
        print(f"  Used: {disk['used_gb']} GB ({disk['usage_percent']}%)")
        print(f"  Free: {disk['free_gb']} GB")
    else:
        print(f"  Error: {disk['error']}")
    print()

    # Memory Usage
    print("Memory Usage:")
    memory = report['memory']
    if 'error' not in memory:
        vm = memory['virtual_memory']
        print(f"  Virtual Memory:")
        print(f"    Total: {vm['total_gb']} GB")
        print(f"    Used: {vm['used_gb']} GB ({vm['usage_percent']}%)")
        print(f"    Available: {vm['available_gb']} GB")

        if memory['swap_memory']['total_gb'] > 0:
            swap = memory['swap_memory']
            print(f"  Swap Memory:")
            print(f"    Total: {swap['total_gb']} GB")
            print(f"    Used: {swap['used_gb']} GB ({swap['usage_percent']}%)")
    else:
        print(f"  Error: {memory['error']}")
    print()

    # CPU Information
    print("CPU Information:")
    cpu = report['cpu']
    if 'error' not in cpu:
        print(f"  Physical Cores: {cpu['physical_cores']}")
        print(f"  Total Cores: {cpu['total_cores']}")
        print(f"  Current Usage: {cpu['cpu_usage_percent']}%")
        print(f"  Current Frequency: {cpu['current_frequency_mhz']} MHz")
    else:
        print(f"  Error: {cpu['error']}")
    print()


def save_report_to_file(report, filename=None):
    """
    Save system report to a JSON file

    Args:
        report (dict): System report to save
        filename (str): Output filename (if None, generates timestamp-based name)
    """
    import json

    if filename is None:
        timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
        filename = f'system_report_{timestamp}.json'

    try:
        with open(filename, 'w') as f:
            json.dump(report, f, indent=2, default=str)
        print(f"Report saved to: {filename}")
        return filename
    except Exception as e:
        print(f"Error saving report: {e}")
        return None


# ============================================================================
# MAIN EXECUTION
# ============================================================================

def main():
    """Main function to demonstrate the utilities"""
    print("System Utilities Script")
    print("=" * 40)

    # Check dependencies
    if not PSUTIL_AVAILABLE:
        print("Warning: Some features require psutil. Install with:")
        print("pip install psutil")
        print()

    # Generate and display report
    report = generate_system_report()
    print_system_report(report)

    # Optionally save to file
    save_choice = input("\nSave report to file? (y/n): ").lower().strip()
    if save_choice == 'y':
        filename = save_report_to_file(report)
        if filename:
            print(f"Report saved successfully!")


if __name__ == "__main__":
    main()