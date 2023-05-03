# Importing necessary modules
import logging
from datetime import date, timedelta

from django.conf import settings
from django.contrib import messages
from django.contrib.auth.decorators import login_required, user_passes_test
from django.contrib.auth.models import Group, User
from django.core.mail import send_mail
from django.db.models import Q, Sum
from django.http import HttpResponse, HttpResponseRedirect
from django.shortcuts import redirect, render, reverse

from insurance import forms as CFORM
from insurance import models as CMODEL
from insurance.models import ClaimRecords, Policy, PolicyRecord
from payments.views import notificationEmail
from . import forms, models

# create logger object
logger = logging.getLogger(__name__)

def customerclick_view(request):
    """
    Renders customer home page.
    """
    if request.user.is_authenticated:
        return HttpResponseRedirect(reverse('afterlogin'))
    return render(request, 'customer/customerclick.html')


def customer_signup_view(request):
    """
    Renders the customer sign up form and handles form submission.

    Sends registration confirmation email to the customer after successful sign up.
    """
    userForm = forms.CustomerUserForm()
    customerForm = forms.CustomerForm()
    mydict = {'userForm': userForm, 'customerForm': customerForm}
    if request.method == 'POST':
        userForm = forms.CustomerUserForm(request.POST)
        customerForm = forms.CustomerForm(request.POST, request.FILES)
        if userForm.is_valid() and customerForm.is_valid():
            user = userForm.save()
            user.set_password(user.password)
            customer = customerForm.save(commit=False)
            customer.user = user
            customer.save()

            # Send email for successful registration
            sub = "Issurance Management-Registration"
            recipients = [user.username]
            emailContext = {"customer": user}
            notificationEmail(emailSubject=sub, recipients=recipients, templateName='registration.html',
                              templateContext=emailContext)

            my_customer_group = Group.objects.get_or_create(name='CUSTOMER')
            my_customer_group[0].user_set.add(user)

            logger.info(f"New customer registered with username {user.username} and email {user.email}")
            return HttpResponseRedirect(reverse('customerlogin'))
    return render(request, 'customer/customersignup.html', context=mydict)


def is_customer(user):
    """
    Returns True if the given user belongs to the CUSTOMER group.
    """
    return user.groups.filter(name='CUSTOMER').exists()


@login_required(login_url='customerlogin')
def customer_dashboard_view(request):
    """
    Renders the customer dashboard, displaying various metrics related to the customer's activity.
    """
    customer = models.Customer.objects.get(user_id=request.user.id)
    available_policy_count = CMODEL.Policy.objects.all().count()
    applied_policy_count = CMODEL.PolicyRecord.objects.all().filter(customer=customer).count()
    total_category_count = CMODEL.Category.objects.all().count()
    total_question_count = CMODEL.Question.objects.all().filter(customer=customer).count()

    dict = {
        'customer': customer,
        'available_policy': available_policy_count,
        'applied_policy': applied_policy_count,
        'total_category': total_category_count,
        'total_question': total_question_count,
    }
    logger.info(f"Customer dashboard loaded for username {request.user.username}")
    return render(request, 'customer/customer_dashboard.html', context=dict)

def apply_policy_view(request):
    """
    View function to apply policy.
    """
    customer = models.Customer.objects.get(user_id=request.user.id)
    policies = CMODEL.Policy.objects.all()
    return render(request,'customer/apply_policy.html',{'customer':customer,'policies':policies})

def apply_claim_view(request):
    """
    View function to apply claim for approved and payment made.
    """
    customer = models.Customer.objects.get(user_id=request.user.id)
    policy_record = CMODEL.PolicyRecord.objects.get(status="Approved")
    return render(request,'customer/customer_insurance_claim.html',{'policy_record':policy_record,'customer':customer})


def policy_detail_view(request, id):
    """
    View to display the details of a policy.
    """

    # Get the customer associated with the request user.
    customer = models.Customer.objects.get(user_id=request.user.id)

    # Get the policy with the specified id.
    policy = CMODEL.Policy.objects.get(id=id)

    # Create a context dictionary with the policy.
    context = {'policies': policy}

    # Log the policy details.
    logger.info(f"Policy name: {policy.policy_name}, ID: {policy.id}")

    # Render the policy detail template with the context.
    return render(request, 'customer/policy_detail.html', context)


def applied_policy_detail_view(request):
    """
    View to display the list of policies available for the customer to apply.
    """

    # Get the customer associated with the request user.
    customer = models.Customer.objects.get(user_id=request.user.id)

    # Get all the policies.
    policies = CMODEL.Policy.objects.all()

    # Render the apply policy template with the policies and customer.
    return render(request, 'customer/apply_policy.html', {'policies': policies, 'customer': customer})


def category_view(request):
    """
    View to display the list of policies available in a category.
    """

    # Get all the policies.
    policies = CMODEL.Policy.objects.all()

    # Render the apply policy template with the policies.
    return render(request, 'customer/apply_policy.html', {'policies': policies})


def apply_view(request, pk):
    """
    View to apply for a policy.
    """

    # Get the customer associated with the request user.
    customer = models.Customer.objects.get(user_id=request.user.id)

    # Get the policy with the specified id.
    policy = CMODEL.Policy.objects.get(id=pk)

    # Create a policy record for the customer and policy.
    policy_record = CMODEL.PolicyRecord.objects.create(customer=customer, policy=policy)

    # Save the policy record.
    policy_record.save()

    # Log that the policy record was saved.
    logger.info("Policy record saved")

    # Redirect to the history view.
    return redirect('history')

def history_view(request):
    # Get the current customer
    customer = CMODEL.Customer.objects.get(user_id=request.user.id)
    
    # Get all policy records for the customer
    policies = CMODEL.PolicyRecord.objects.all().filter(customer=customer)
    
    # Log policy details for each record
    for policy in policies:
        logger.info(f"Policy details for customer {customer.id}: Category: {policy.policy.category}, Policy Name: {policy.policy.policy_name}")
    
    # Render the history page with policy records and customer details
    return render(request,'customer/history.html',{'policies':policies,'customer':customer})

def ask_question_view(request):
    # Get the current customer
    customer = CMODEL.Customer.objects.get(user_id=request.user.id)
    
    # Create a new question form
    questionForm=CFORM.QuestionForm() 
    
    if request.method=='POST':
        # If the form has been submitted, validate the data
        questionForm=CFORM.QuestionForm(request.POST)
        if questionForm.is_valid():
            # If the form is valid, save the question and redirect to the question history page
            
            # Create a new question object and associate it with the current customer
            question = questionForm.save(commit=False)
            question.customer=customer
            question.save()
            
            # Log the creation of the question
            logger.info(f"Question {question.id} created by customer {customer.id}")
            
            return redirect('question-history')
    # Render the ask question page with the question form and customer details
    return render(request,'customer/ask_question.html',{'questionForm':questionForm,'customer':customer})

def question_history_view(request):
    # Get the current customer
    customer = CMODEL.Customer.objects.get(user_id=request.user.id)
    
    # Get all questions for the current customer
    questions = CMODEL.Question.objects.all().filter(customer=customer)
    
    # Render the question history page with questions and customer details
    return render(request,'customer/question_history.html',{'questions':questions,'customer':customer})


def submit_claim_view(request,id):
    """
    A view function to submit a claim request for a particular policy by a customer.

    """

    policy =  Policy.objects.get(id=id)

    if request.method == 'POST':
        # Get the customer who is submitting the claim
        customer = Customer.objects.get(user_id = request.user.id)
        # Get the policy for which the claim request is being made
        policy = Policy.objects.get(policy_name = policy)

        # Check if the customer has already made a claim request for the same policy
        claim_record = ClaimRecords.objects.get(policy=policy)

        if customer and claim_record:
            logger.info("denied   cannot buy same policy same user again and again")

        # Get the form data and save the claim request to the database
        f_name = request.POST.get('fname')  
        l_name = request.POST.get('lname')
        nation = request.POST.get('former_nationalty')
        age  = request.POST.get('age')
        pan_no  = request.POST.get('pan_no')
        aadhar_no  = request.POST.get('aadhar_no')
        policy_issue_date  = request.POST.get('policy_issue_date')
        policy_expire_date  = request.POST.get('policy_expire_date')
        home_address  = request.POST.get('home_address')
        telephone_number  = request.POST.get('telephone_number')
        email_address  = request.POST.get('email_address')
        gender  = request.POST.get('gender')
        maritial_status  = request.POST.get('maritial_status')
        relation = request.POST.get('relation')
        policy_nominee = request.POST.get('policy_nominee')
        sum_assured = request.POST.get('sum_assured')
        claim_amount = request.POST.get('claim_amount')
        claim_description = request.POST.get('claim_description')
        image1 = request.FILES['image1']
        image2 = request.FILES['image2']
        image3 = request.FILES['image3']
        image4 = request.FILES['image4']

        # Create and save the claim record
        claim = ClaimRecords.objects.create( customer = customer, policy = policy,firstName = f_name, 
                                            lastName=l_name,nationality=nation,age=age,pan_no = pan_no,aadhar_no = aadhar_no,contact_add=home_address,
                                            telephone=telephone_number,email=email_address,sex=gender,martial_status=maritial_status,
                                            policy_holder_relation = relation,policy_holder_nominee=policy_nominee,sum_assured=sum_assured,
                                            claim_amount = claim_amount,claim_desc =claim_description,image1=image1,image2=image2,image3=image3, image4=image4 )
        claim.save()
        messages.success(request, 'You already hold a verified account with us. Please login.')

        # Redirect to the customer dashboard
        return redirect("customer-dashboard")

    # Render the admin_submit_claim.html template with the policy details
    return render(request,'customer/admin_submit_claim.html',{"policy":policy})
