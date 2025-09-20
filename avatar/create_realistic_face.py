import cv2
import numpy as np

# Create a more realistic default face
img = np.ones((512, 512, 3), dtype=np.uint8) * 240

# Face shape (oval)
cv2.ellipse(img, (256, 280), (120, 160), 0, 0, 360, (220, 200, 180), -1)

# Eyes
cv2.ellipse(img, (220, 220), (25, 15), 0, 0, 360, (255, 255, 255), -1)
cv2.ellipse(img, (292, 220), (25, 15), 0, 0, 360, (255, 255, 255), -1)
cv2.circle(img, (220, 220), 12, (100, 80, 60), -1)
cv2.circle(img, (292, 220), 12, (100, 80, 60), -1)
cv2.circle(img, (220, 218), 4, (0, 0, 0), -1)
cv2.circle(img, (292, 218), 4, (0, 0, 0), -1)

# Eyebrows
cv2.ellipse(img, (220, 200), (20, 8), 0, 0, 180, (120, 100, 80), -1)
cv2.ellipse(img, (292, 200), (20, 8), 0, 0, 180, (120, 100, 80), -1)

# Nose
cv2.ellipse(img, (256, 250), (12, 20), 0, 0, 180, (200, 180, 160), -1)

# Mouth
cv2.ellipse(img, (256, 300), (30, 15), 0, 0, 180, (180, 120, 120), -1)

# Hair
cv2.ellipse(img, (256, 180), (130, 80), 0, 0, 180, (80, 60, 40), -1)

cv2.imwrite('/app/default_face.jpg', img)
print("Realistic face created")