# Volunteer Task Assignment and Completion System

## Overview

The volunteer system now supports:

1. **Task Assignment** - Assign specific tasks to individual volunteers
2. **Task Completion Tracking** - Automatically mark tasks as completed
3. **One-Time vs Recurring Tasks** - Handle both temporary and ongoing tasks
4. **Admin Quick Actions** - Easy task management from admin interface

## New Task Features

### Task Types

**One-Time Tasks:**

- Disappear from the available task list once completed
- Perfect for specific projects or repairs
- Examples: "Fix broken shelf", "Paint storage room door"

**Recurring Tasks:**

- Always remain available even after completion
- Good for ongoing maintenance and regular activities
- Examples: "Clean workshop area", "Check safety equipment"

### Task Assignment

**Assign to Specific Volunteer:**

- Tasks can be assigned to individual volunteers
- Only the assigned volunteer sees the task (unless admin)
- Helps ensure important tasks get done by qualified people
- Unassigned tasks are visible to all volunteers

### Task Status Tracking

**Available Statuses:**

- **Available** - Ready to be worked on
- **In Progress** - Someone is currently working on it
- **Completed** - Task has been finished
- **On Hold** - Temporarily paused

## How It Works

### For Volunteers

**During Sign-In:**

- See preview of available tasks including:
  - Tasks assigned specifically to them (highlighted)
  - General tasks available to everyone
  - Task type indicators (one-time vs recurring)
  - Priority levels and estimated duration

**During Sign-Out:**

- Select tasks they actually worked on
- System automatically marks one-time tasks as completed
- Recurring tasks remain available for future sessions

### For Administrators

**Creating Tasks:**

1. Go to **Volunteering → Tasks → Add New Task**
2. Fill in basic task information
3. **New Fields:**
   - **Task Type:** Choose "One-Time" or "Recurring"
   - **Assigned To:** Select specific volunteer (optional)
   - **Task Status:** Set current status
   - **Completion tracking fields** (auto-filled when completed)

**Managing Tasks:**

- **Quick Complete:** Mark tasks as done with one click
- **Reopen Tasks:** Restore completed tasks to available status
- **View Assignments:** See who tasks are assigned to
- **Track Completion:** See who completed tasks and when

## Task Assignment Workflow

### Assigning Tasks

1. **Create or Edit Task:**

   - Go to task edit page
   - Select volunteer in "Assigned To" field
   - Save task

2. **Task Visibility:**
   - Assigned volunteer sees task highlighted as "Assigned to you"
   - Other volunteers don't see assigned tasks
   - Admins can see all tasks regardless of assignment

### Completing Tasks

**Automatic Completion (Recommended):**

- Volunteer selects task during sign-out
- System automatically marks one-time tasks as completed
- Records completion date, volunteer, and notes

**Manual Completion (Admin):**

- Go to **Volunteering → Tasks**
- Click **"Mark Complete"** or **"Log Completion"** button
- Add optional completion notes
- Task status updates immediately

## Admin Interface Features

### Task List Enhancements

**Visual Indicators:**

- **Task Type Badges:** 🔄 Recurring or 📋 One-time
- **Status Badges:** Color-coded status indicators
- **Assignment Info:** Shows who task is assigned to
- **Completion Info:** Shows who completed task and when

**Quick Actions:**

- **Mark Complete/Log Completion:** Instant task completion
- **Reopen:** Restore completed tasks to available status
- **Edit:** Full task editing capabilities
- **Stats:** View completion statistics

### Task Status Colors

- **Available:** Blue
- **In Progress:** Yellow
- **Completed:** Green
- **On Hold:** Gray

## Use Cases

### One-Time Tasks Examples

```
Task: Fix Broken 3D Printer
Type: One-Time
Assigned To: John (3D Printing Expert)
Priority: High
Status: Available → In Progress → Completed

Result: Task disappears from list once John completes it
```

### Recurring Tasks Examples

```
Task: Weekly Safety Check
Type: Recurring
Assigned To: Sarah (Safety Officer)
Priority: High
Status: Always Available

Result: Task remains available every week for Sarah to complete
```

### Unassigned Tasks

```
Task: Organize Storage Room
Type: One-Time
Assigned To: (None - available to all)
Priority: Medium
Status: Available

Result: Any volunteer can work on this task
```

## Best Practices

### Task Assignment

**When to Assign Tasks:**

- Tasks requiring specific skills or certifications
- Important tasks that must be done by qualified people
- Tasks with deadlines that need ownership
- Specialized equipment maintenance

**When NOT to Assign:**

- General cleaning and organization tasks
- Tasks any volunteer can safely do
- Tasks you want multiple people to help with

### Task Types

**Use One-Time for:**

- Specific repairs or fixes
- Project-based work with clear endpoints
- Seasonal or event-related tasks
- Tasks that only need to be done once

**Use Recurring for:**

- Regular maintenance activities
- Daily/weekly/monthly cleaning tasks
- Ongoing safety checks
- Tasks that need to be done repeatedly

### Task Management

**Regular Review:**

- Check completed one-time tasks monthly
- Review recurring task completion frequency
- Reassign tasks if volunteers are unavailable
- Update task priorities based on needs

**Clear Instructions:**

- Provide detailed step-by-step instructions
- Include required tools and materials
- Set realistic time estimates
- Add safety notes when needed

## Troubleshooting

### Tasks Not Showing

**For Assigned Tasks:**

- Verify task is assigned to correct user
- Check that task status is "Available" or "In Progress"
- Ensure volunteer account is active

**For All Tasks:**

- Check task is published
- Verify task status is not "Completed" (for one-time tasks)
- Check if volunteer has required certifications

### Completion Issues

**Tasks Not Marking Complete:**

- Ensure volunteer selected task during sign-out
- Check that task type is set correctly
- Verify completion functions are working (System Test page)

**Wrong Completion Status:**

- Use "Reopen" button to reset task
- Edit task to correct assignment or type
- Check completion notes for details

## Database Fields

### New ACF Fields Added

- `task_type` - "one_time" or "recurring"
- `assigned_to` - User ID of assigned volunteer
- `task_status` - Current status of task
- `completed_by` - User ID who completed task
- `completed_date` - When task was completed
- `completion_notes_actual` - Notes from volunteer who completed task

### Backward Compatibility

- Existing tasks default to "one_time" type
- Existing tasks default to "available" status
- No assigned volunteer means task is available to all
- System works with or without new fields

The task assignment and completion system provides powerful tools for managing both one-off projects and ongoing maintenance while maintaining the flexibility volunteers need.
