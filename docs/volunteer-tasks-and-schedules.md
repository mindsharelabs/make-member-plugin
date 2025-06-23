# Volunteer Tasks and Schedules Guide

## Overview

The volunteer system includes two key features:

1. **Task Selection During Sign-Out** - Volunteers can select what they worked on when signing out
2. **Volunteer Schedules** - Admin can set regular volunteer schedules and track adherence

## 1. Volunteer Tasks - "What to Work On"

### How It Works

**During Sign-In (Task Preview):**
When a volunteer selects "Volunteering" and signs in, they immediately see:

1. **Welcome Message:**

   - Personalized greeting with their name
   - Session start confirmation
   - Schedule status (on time, early, late, or unscheduled)

2. **"What needs to be done today" Preview:**
   - Up to 6 available tasks, prioritized by urgency
   - Task titles, categories, and brief descriptions
   - Estimated duration and location for each task
   - Priority indicators (color-coded: red=urgent, orange=high, yellow=medium, green=low)
   - Certification requirements noted if applicable
   - Note that they can select completed tasks when signing out

**During Sign-Out (Task Selection):**
When volunteers later click their profile card to sign out, they see:

1. **Current Session Info:**

   - When they signed in
   - How long they've been volunteering
   - Schedule status confirmation

2. **Interactive Task Selection:**
   - All published volunteer tasks in a selectable grid
   - Task categories, descriptions, and details
   - Ability to select multiple tasks they actually worked on
   - Tasks requiring special certifications are disabled if volunteer doesn't have them
   - Optional notes field for additional session details

### Task Management (Admin)

**Creating Tasks:**

1. Go to **Volunteering → Tasks** in WordPress admin
2. Click **"Add New Task"**
3. Fill in task details:
   - **Title:** Clear, descriptive name
   - **Description:** What needs to be done
   - **Category:** Maintenance, Organization, Events, etc.
   - **Priority:** Low, Medium, High, Urgent
   - **Estimated Duration:** How long it should take (in minutes)
   - **Location:** Where in the space (e.g., "Wood Shop", "Front Desk")
   - **Tools Needed:** Required tools or materials
   - **Instructions:** Step-by-step details
   - **Required Skills:** Any badges/certifications needed

**Task Categories:**

- **Maintenance:** General upkeep and repairs
- **Organization:** Cleaning and organizing spaces
- **Events:** Event setup and support
- **Teaching:** Assisting with classes
- **Administrative:** Office and paperwork tasks
- **Safety:** Safety inspections and improvements

### Example Tasks

```
Title: Clean Workshop Area
Category: Maintenance
Priority: Medium
Duration: 30 minutes
Location: Main Workshop
Tools: Broom, cleaning supplies, rags
Instructions:
1. Clear all tools from work surfaces
2. Sweep floor thoroughly
3. Wipe down all work surfaces
4. Organize tools back to proper locations
```

## 2. Volunteer Schedules

### How Schedules Work

**Schedule Adherence Tracking:**

- **On Time:** Signed in within 15 minutes of scheduled start time ✅
- **Early:** Signed in more than 15 minutes before scheduled time ⏰
- **Late:** Signed in more than 15 minutes after scheduled time ⏰
- **Unscheduled:** No regular schedule set for this day/time 📅

**Benefits:**

- Helps track volunteer reliability
- Ensures adequate coverage during key hours
- Provides structure for regular volunteers
- Flexible volunteers can still volunteer anytime

### Adding Volunteer Schedules (Admin)

1. **Go to Volunteering → Schedules**
2. **Use the "Add New Volunteer Schedule" form:**
   - **Volunteer:** Select from dropdown of existing volunteers
   - **Day of Week:** Sunday through Saturday
   - **Start Time:** When their shift begins
   - **End Time:** When their shift ends
3. **Click "Add Schedule"**

### Schedule Management Features

**Weekly Overview:**

- Visual grid showing all scheduled volunteers by day
- Easy to see coverage gaps
- Quick remove buttons for each schedule

**Schedule List:**

- Table view of all schedules
- Shows volunteer name, day, times, and status
- Remove schedules that are no longer needed

**Multiple Shifts:**

- Volunteers can have multiple scheduled shifts per week
- Each shift is tracked independently

### Example Schedule Setup

```
Volunteer: John Smith
Day: Monday
Start Time: 10:00 AM
End Time: 2:00 PM

Volunteer: Sarah Johnson
Day: Wednesday
Start Time: 6:00 PM
End Time: 9:00 PM

Volunteer: Mike Davis
Day: Saturday
Start Time: 9:00 AM
End Time: 1:00 PM
```

## 3. Complete Volunteer Workflow

### For Volunteers:

1. **Sign In:**

   - Click your profile card
   - Select "Volunteering" badge
   - System starts tracking your session
   - **See task preview** - View up to 6 priority tasks that need attention today

2. **Work on Tasks:**

   - Use the task preview from sign-in to guide your work
   - Work on various projects around the space
   - Focus on urgent/high priority items when possible

3. **Sign Out:**

   - Click your profile card again
   - Select tasks you completed
   - Add any notes about your session
   - Click "Sign Out"

4. **Session Summary:**
   - See total time volunteered
   - View completed tasks
   - Schedule adherence status

### For Administrators:

1. **Create Tasks:**

   - Add volunteer tasks with clear instructions
   - Set priorities and estimated durations
   - Assign required skills if needed

2. **Set Schedules:**

   - Add regular volunteer schedules
   - Monitor schedule adherence
   - Adjust schedules as needed

3. **Monitor Activity:**
   - View active volunteer sessions
   - Check volunteer hours and statistics
   - Generate reports for volunteer coordination

## 4. System Requirements

**Before Using:**

1. **Database Tables:** Must be created (use System Test page)
2. **Task Permissions:** Must be granted (use System Test page)
3. **Default Tasks:** Create some initial tasks for volunteers to select

**Setup Steps:**

1. Go to **Volunteering → System Test**
2. Click **"Create/Update Volunteer Tables"**
3. Click **"Add Task Creation Permissions"**
4. Click **"Create Default Volunteer Tasks"**
5. Go to **Volunteering → Schedules** to add volunteer schedules

## 5. Troubleshooting

**Tasks Not Showing:**

- Check if volunteer tasks exist (Volunteering → Tasks)
- Verify tasks are published
- Check if volunteer has required badges for restricted tasks

**Schedule Issues:**

- Ensure database tables exist
- Check that schedules are set to "Active"
- Verify volunteer user accounts exist

**Permission Problems:**

- Use System Test page to add task creation permissions
- Check user roles (Administrator and Editor can create tasks)

## 6. Best Practices

**Task Creation:**

- Keep task titles clear and specific
- Include estimated durations to help volunteers plan
- Add detailed instructions for complex tasks
- Use appropriate priority levels

**Schedule Management:**

- Set realistic time expectations
- Allow some flexibility in schedule adherence
- Regularly review and update schedules
- Communicate schedule changes to volunteers

**Volunteer Engagement:**

- Create a variety of task types and difficulties
- Recognize volunteers who complete many tasks
- Use the reporting features to track volunteer impact
- Provide feedback on completed tasks when possible

The volunteer system is designed to be flexible while providing structure and tracking for effective volunteer management.
