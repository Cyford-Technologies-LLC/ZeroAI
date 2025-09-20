import requests
import cv2
import numpy as np

# Download a real human face photo
try:
    # Use a free stock photo API
    url = "https://thispersondoesnotexist.com/image"
    response = requests.get(url, timeout=10)
    
    if response.status_code == 200:
        # Save the image
        with open('/app/real_face.jpg', 'wb') as f:
            f.write(response.content)
        
        # Load and resize to standard size
        img = cv2.imread('/app/real_face.jpg')
        if img is not None:
            # Resize to 512x512
            img_resized = cv2.resize(img, (512, 512))
            cv2.imwrite('/app/default_face.jpg', img_resized)
            print("Real human face downloaded and set as default")
        else:
            print("Failed to load downloaded image")
    else:
        print(f"Failed to download: {response.status_code}")
        
except Exception as e:
    print(f"Download failed: {e}")
    print("Using fallback method...")
    
    # Fallback: Create a more realistic drawn face
    img = np.ones((512, 512, 3), dtype=np.uint8) * 250
    
    # More realistic face proportions
    cv2.ellipse(img, (256, 280), (120, 160), 0, 0, 360, (235, 220, 200), -1)
    
    # Realistic eyes
    cv2.ellipse(img, (210, 240), (35, 25), 0, 0, 360, (255, 255, 255), -1)
    cv2.ellipse(img, (302, 240), (35, 25), 0, 0, 360, (255, 255, 255), -1)
    cv2.circle(img, (210, 240), 18, (100, 150, 200), -1)
    cv2.circle(img, (302, 240), 18, (100, 150, 200), -1)
    cv2.circle(img, (210, 240), 10, (20, 20, 20), -1)
    cv2.circle(img, (302, 240), 10, (20, 20, 20), -1)
    
    # Eyebrows
    cv2.ellipse(img, (210, 215), (30, 10), 0, 0, 180, (120, 90, 70), -1)
    cv2.ellipse(img, (302, 215), (30, 10), 0, 0, 180, (120, 90, 70), -1)
    
    # Nose
    cv2.ellipse(img, (256, 270), (18, 30), 0, 0, 360, (225, 210, 190), -1)
    
    # Mouth
    cv2.ellipse(img, (256, 330), (40, 15), 0, 0, 180, (200, 150, 150), -1)
    
    # Hair
    cv2.ellipse(img, (256, 180), (130, 80), 0, 0, 180, (100, 80, 60), -1)
    
    cv2.imwrite('/app/default_face.jpg', img)
    print("Realistic drawn face created")