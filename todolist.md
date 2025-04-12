# Development Todolist

## Phase 1: Core Infrastructure (Completed)
- [x] Set up basic routing system
- [x] Implement user authentication
- [x] Create database schema for ads, users, and billing
- [x] Set up API endpoints structure
- [x] Implement basic CRUD operations for ads

## Phase 2: Ad Management (In Progress)
- [x] Create ad iframe delivery system
- [x] Implement ad tracking mechanism (Note: Focus on views/impressions for reporting, not direct billing)
- [x] Build basic ad preview functionality
- [x] Set up ad approval workflow
- [x] Create ad status management system (Active based on duration)
- [ ] Implement ad content creation using Quill Editor
- [ ] Store ad content as Quill Delta JSON
- [ ] Implement server-side rendering of Quill Delta to HTML for iframe delivery
- [ ] Add duration-based ad scheduling (start/end dates or purchased duration)
- [ ] ~~Implement ad targeting system~~ (Keep for future, but lower priority than core billing change)
  - [ ] ~~Geographic targeting~~
  - [ ] ~~Device targeting~~
  - [ ] ~~Time-based targeting~~
- [ ] ~~Add budget control system~~ (Replaced by duration billing)
  - [ ] ~~Daily budget limits~~
  - [ ] ~~Total budget tracking~~
  - [ ] ~~Cost per view/click calculation~~
- [ ] ~~Create ad rotation system~~ (Keep for future, lower priority)
  - [ ] ~~Weight-based rotation~~
  - [ ] ~~Performance-based optimization~~
  - [ ] ~~A/B testing support~~
- [ ] ~~Add fraud detection~~ (Keep for future, lower priority)
  - [ ] ~~Click fraud detection~~
  - [ ] ~~Invalid traffic filtering~~
  - [ ] ~~IP-based restrictions~~

## Phase 3: Ad Editor (Needs Revision)
- [ ] Design editor interface (Using Quill)
- [ ] Implement Quill editor component
- [x] ~~Implement canvas-based editing~~ (Replaced by Quill)
- [ ] ~~Add template system~~ (Re-evaluate need with Quill)
- [x] ~~Create layer management~~ (Not applicable to Quill)
- [x] ~~Add text editing tools~~ (Provided by Quill)
- [x] ~~Implement image manipulation~~ (Provided by Quill or separate upload)
- [x] ~~Add save/export functionality~~ (Save Quill Delta JSON)

## Phase 4: Billing System (Needs Major Revision)
- [ ] Implement Duration-Based Billing Logic
- [ ] Track ad activation based on purchased duration
- [ ] Create CDKEY Generation System (Admin)
- [ ] Create CDKEY Redemption Endpoint/Mechanism (Advertiser)
- [ ] Add `cdkeys` database table
- [ ] ~~Create credit system~~ (May be replaced or adapted for CDKEY value)
- [x] Implement view tracking (For reporting purposes)
- [x] Set up billing API (Needs revision for duration/CDKEYs)
- [x] Add reporting system (Reflect duration, CDKEY usage)
- [x] Implement transaction logging (For CDKEY redemption, duration purchases)
- [ ] Add real-time balance updates (If credits are kept alongside duration)
- [ ] Implement automatic billing alerts (e.g., duration expiring soon)
- [ ] ~~Create refund mechanism~~ (Define policy for duration-based billing)

## Phase 5: Admin Features (Next Up)
- [ ] Build admin dashboard (Static HTML + API)
  - [ ] Ad performance overview (Impressions, Active Duration)
  - [ ] User management interface
  - [ ] System statistics
  - [ ] CDKEY Management Interface (Generation, Status)
- [ ] Create analytics system
  - [ ] Real-time tracking
  - [ ] Performance reports
  - [ ] Revenue analytics
- [ ] Implement ad review interface
- [ ] Add user management
- [ ] Create system reports

## Phase 6: Publisher Features (Next Up)
- [ ] Create publisher dashboard (Static HTML + API)
- [ ] Add site management
- [ ] Implement earnings tracking (If publishers get paid, otherwise N/A)
- [ ] Create ad placement manager (Get iframe code)
- [ ] ~~Add payment system and middleware to allow site owner use middleware connect with different payment method~~ (Deferring complex payment integrations)

## Phase 7: Optimization and Security (Adjust Priorities)
- [ ] Implement caching system (Especially for rendered ad HTML)
- [ ] Add rate limiting
- [ ] Set up CDN for assets
- [ ] Implement CSRF protection
- [ ] Add input validation
- [ ] Set up error logging
- [ ] Implement backup system

## Phase 8: Documentation and Testing
- [ ] Write API documentation
- [ ] Create user guides
- [ ] Write integration guides
- [ ] Perform security testing
- [ ] Run load testing
- [ ] Create automated tests

## Current Focus (Core Functionality Revision)
1.  Implement Quill Ad Editor & Server-Side Rendering
2.  Implement Duration-Based Billing Logic
3.  Implement CDKEY System (Generation & Redemption)
4.  Refactor Database Schema (Ads table for duration, add CDKEYs table)
5.  Update Core API Endpoints (Ad CRUD, Redemption)

## Next Steps
1.  Build Admin Interface for CDKEY Generation & User Management (Static HTML + API)
2.  Build Advertiser Interface for Ad Creation (Quill) & CDKEY Redemption (Static HTML + API)
3.  Build Publisher Interface for Ad Zone/Placement Management (Static HTML + API)
4.  Refine Ad Serving API (Deliver rendered Quill HTML in iframe)
5.  Develop Basic Reporting for Admins/Advertisers (Impressions, Duration Usage)

## Upcoming Tasks (Post Core Revision)
1.  Implement Ad Approval Workflow (Admin)
2.  Implement Ad Targeting (Geo, Device, Time)
3.  Implement Ad Rotation/Optimization
4.  Add Fraud Detection
5.  Develop Advanced Analytics/Reporting
6.  Refine Security (Rate Limiting, Input Validation etc.)
7.  Add CDN support for uploaded images (used in Quill) 