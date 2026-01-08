// Email masking
echo mask('user@gmail.com', 'email'); 
// Output: u***@g****.com

// Phone masking
echo mask('080-1234-5678', 'phone'); 
// Output: 080***5678

// Card masking
echo mask('1234 5678 9012 3456', 'card'); 
// Output: **** **** **** 3456

// Custom masking with different char
echo mask('MyPassword123', 'custom', '#', 2, 2); 
// Output: My#########23

// Full masking
echo mask('HideThis', 'full'); 
// Output: ********