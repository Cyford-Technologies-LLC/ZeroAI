
import re

def validate_email(email: str) -> bool:
    """
    Validates an email address.

    This function checks if the given email address is valid according to a simplified set of rules:
    1. It must contain exactly one '@' symbol.
    2. The local part (before '@') must not be empty and can contain letters, digits, and some special characters.
    3. The domain part (after '@') must not be empty and can contain letters, digits, dots, and hyphens.
    4. The domain must have at least one dot and the last part after the dot must be 2-4 characters long.

    Args:
    email (str): The email address to validate.

    Returns:
    bool: True if the email is valid, False otherwise.

    Examples:
    >>> validate_email("user@example.com")
    True
    >>> validate_email("invalid.email@com")
    False
    """
    pattern = r'^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,4}$'
    if re.match(pattern, email):
        parts = email.split('@')
        if len(parts) == 2 and all(parts):
            return True
    return False