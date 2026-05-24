#include<iostream>
using namespace std;

int main()
{ int num;
  long int facto =1;
  cout<<"Enter a number to calculate its factorial (-1 to exit) :";
  cin >> num;
  
  while(num != -1)
  {   if(num > 0)
         {  for(int i = 2; i <= num; i++)
             { facto = facto * i; }  
	         cout <<"The factorial of" << num << " = " << facto << endl;
	     }
	   else
	   { cout<<"Factorial not calculated for negative input or zero"<<endl;
	   }
    cout<<<"Enter a number to calculate its factorial (-1 to exit) :";
    cin >> num;
  } //end of while loop

	   
	   return 0;
}
