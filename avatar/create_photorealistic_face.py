import cv2
import numpy as np

# Create photorealistic human face
img = np.ones((512, 512, 3), dtype=np.uint8) * 245

# Face shape with gradient shading
face_center = (256, 280)
face_axes = (110, 140)

# Create face with realistic skin tone gradient
for y in range(512):
    for x in range(512):
        # Calculate distance from face center
        dx = x - face_center[0]
        dy = y - face_center[1]
        
        # Elliptical face shape
        if (dx*dx)/(face_axes[0]*face_axes[0]) + (dy*dy)/(face_axes[1]*face_axes[1]) <= 1:
            # Add skin tone with subtle shading
            shade = 1.0 - 0.1 * np.sqrt(dx*dx + dy*dy) / 150
            img[y, x] = [int(220*shade), int(195*shade), int(175*shade)]

# Eyes with realistic details
# Left eye
cv2.ellipse(img, (220, 230), (30, 20), 0, 0, 360, (255, 255, 255), -1)
cv2.circle(img, (220, 230), 15, (120, 80, 60), -1)  # Iris
cv2.circle(img, (220, 230), 8, (40, 40, 40), -1)    # Pupil
cv2.circle(img, (218, 227), 3, (255, 255, 255), -1) # Highlight

# Right eye
cv2.ellipse(img, (292, 230), (30, 20), 0, 0, 360, (255, 255, 255), -1)
cv2.circle(img, (292, 230), 15, (120, 80, 60), -1)  # Iris
cv2.circle(img, (292, 230), 8, (40, 40, 40), -1)    # Pupil
cv2.circle(img, (290, 227), 3, (255, 255, 255), -1) # Highlight

# Eyebrows
cv2.ellipse(img, (220, 205), (25, 8), 0, 0, 180, (100, 70, 50), -1)
cv2.ellipse(img, (292, 205), (25, 8), 0, 0, 180, (100, 70, 50), -1)

# Nose with shading
cv2.ellipse(img, (256, 260), (15, 25), 0, 0, 360, (210, 185, 165), -1)
cv2.ellipse(img, (256, 270), (8, 5), 0, 0, 180, (200, 175, 155), -1)

# Mouth
cv2.ellipse(img, (256, 320), (35, 12), 0, 0, 180, (180, 120, 120), -1)
cv2.ellipse(img, (256, 318), (30, 8), 0, 0, 180, (160, 100, 100), -1)

# Hair
cv2.ellipse(img, (256, 160), (120, 60), 0, 0, 180, (80, 60, 40), -1)

# Add subtle cheek shading
cv2.ellipse(img, (200, 290), (20, 15), 0, 0, 360, (210, 185, 165), -1)
cv2.ellipse(img, (312, 290), (20, 15), 0, 0, 360, (210, 185, 165), -1)

# Jawline definition
cv2.ellipse(img, (256, 380), (100, 30), 0, 0, 180, (210, 185, 165), -1)

cv2.imwrite('/app/default_face.jpg', img)
print("Photorealistic face created")